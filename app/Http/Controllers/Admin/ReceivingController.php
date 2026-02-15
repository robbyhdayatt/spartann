<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lokasi;
use App\Models\Receiving;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReceivingController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $query = Receiving::with(['purchaseOrder', 'lokasi', 'receivedBy']);

        if (!$user->hasRole(['SA', 'PIC', 'MA'])) {
            $query->where('lokasi_id', $user->lokasi_id);
        }

        $receivings = $query->latest()->paginate(15);
        return view('admin.receivings.index', compact('receivings'));
    }

    public function create()
    {
        $this->authorize('perform-warehouse-ops');
        $user = Auth::user();

        // Hanya PO yang sudah APPROVE atau PARTIAL yang bisa diterima
        $query = PurchaseOrder::whereIn('status', ['APPROVED', 'PARTIALLY_RECEIVED']);

        if (!$user->hasRole(['SA', 'PIC', 'MA'])) {
            $query->where('lokasi_id', $user->lokasi_id);
        }

        $purchaseOrders = $query->with(['supplier', 'sumberLokasi'])
            ->orderBy('tanggal_po', 'desc')
            ->get();

        return view('admin.receivings.create', compact('purchaseOrders'));
    }

    public function store(Request $request)
    {
        $this->authorize('perform-warehouse-ops');
        $user = Auth::user();

        $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'tanggal_terima' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.qty_terima' => 'required|integer|min:0',
        ]);

        // Cek total input
        if (collect($request->items)->sum('qty_terima') <= 0) {
            return back()->with('error', 'Jumlah terima tidak boleh kosong semua.')->withInput();
        }

        DB::beginTransaction();
        try {
            // 1. LOCK PO UNTUK MENCEGAH DOUBLE RECEIVE
            $po = PurchaseOrder::with('details')->lockForUpdate()->findOrFail($request->purchase_order_id);

            // Validasi Lokasi Penerima
            if ($user->lokasi_id && $user->lokasi_id != $po->lokasi_id) {
                throw new \Exception("Anda tidak berhak menerima barang untuk lokasi ini.");
            }

            // Validasi Status PO
            if (!in_array($po->status, ['APPROVED', 'PARTIALLY_RECEIVED'])) {
                throw new \Exception("PO ini tidak dalam status siap diterima (Status: {$po->status}).");
            }

            // Tentukan Flow: Dealer langsung Putaway, Pusat masuk QC
            // Sesuaikan logic ini dengan kebutuhan bisnis Anda
            $isDealer = $user->hasRole(['PC', 'KC', 'KSR', 'AD']) || optional($po->lokasi)->tipe === 'DEALER';
            $initialStatus = $isDealer ? 'PENDING_PUTAWAY' : 'PENDING_QC';
            $qcBy = $isDealer ? $user->id : null; // Auto QC pass for Dealer
            $qcAt = $isDealer ? now() : null;

            // Buat Header Receiving
            $receiving = Receiving::create([
                'purchase_order_id' => $po->id,
                'lokasi_id' => $po->lokasi_id,
                'nomor_penerimaan' => Receiving::generateReceivingNumber(),
                'tanggal_terima' => $request->tanggal_terima,
                'status' => $initialStatus,
                'catatan' => $request->catatan,
                'received_by' => $user->id,
                'qc_by' => $qcBy,
                'qc_at' => $qcAt
            ]);

            $totalDiterimaSesiIni = 0;

            // Proses Setiap Item
            foreach ($request->items as $barangId => $itemData) {
                $qtyTerima = (int)$itemData['qty_terima'];
                if ($qtyTerima <= 0) continue;

                $poDetail = $po->details->firstWhere('barang_id', $barangId);

                if (!$poDetail) {
                    throw new \Exception("Item ID {$barangId} tidak ada dalam PO ini.");
                }

                // VALIDASI OVER-RECEIVING
                $sisaBolehDiterima = $poDetail->qty_pesan - $poDetail->qty_diterima;
                if ($qtyTerima > $sisaBolehDiterima) {
                    throw new \Exception("Over-Receiving pada {$poDetail->barang->part_name}. Sisa: {$sisaBolehDiterima}, Input: {$qtyTerima}.");
                }

                // Update PO Detail
                $poDetail->increment('qty_diterima', $qtyTerima);
                $totalDiterimaSesiIni += $qtyTerima;

                // Buat Receiving Detail
                $receiving->details()->create([
                    'barang_id' => $barangId,
                    'qty_terima' => $qtyTerima,
                    'qty_pesan_referensi' => $poDetail->qty_pesan,
                    'qty_lolos_qc' => $isDealer ? $qtyTerima : 0, // Dealer auto lolos
                    'qty_gagal_qc' => 0
                ]);
            }

            // Sync Status PO (Partial / Full)
            $po->syncStatus(); // Panggil method di model PO

            DB::commit();

            $msg = $isDealer 
                ? 'Penerimaan berhasil (Auto QC). Lanjut ke Putaway.' 
                : 'Penerimaan berhasil. Lanjut ke QC.';
                
            $route = $isDealer ? 'admin.putaway.index' : 'admin.receivings.index';

            return redirect()->route($route)->with('success', $msg);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error: ' . $e->getMessage())->withInput();
        }
    }

    public function show(Receiving $receiving)
    {
        $receiving->load(['purchaseOrder.supplier', 'details.barang', 'createdBy', 'receivedBy', 'lokasi']);
        $stockMovements = $receiving->stockMovements()->with(['rak', 'user', 'barang'])->get();
        return view('admin.receivings.show', compact('receiving', 'stockMovements'));
    }
    
    // API Helper untuk mengisi form create via AJAX
    public function getPurchaseOrderDetails(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('perform-warehouse-ops');
        
        $details = $purchaseOrder->details()->with('barang')->get()
            ->map(function($d) {
                $d->sisa = $d->qty_pesan - $d->qty_diterima;
                return $d;
            })
            ->filter(function($d) {
                return $d->sisa > 0;
            })
            ->values();

        return response()->json($details);
    }
}