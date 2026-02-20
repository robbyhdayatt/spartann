<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lokasi;
use App\Models\Receiving;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReceivingController extends Controller
{
    public function index()
    {
        $this->authorize('view-receiving');
        
        $user = Auth::user();
        $query = Receiving::with(['purchaseOrder.supplier', 'purchaseOrder.sumberLokasi', 'lokasi', 'receivedBy']);

        // Filter Lokasi: User hanya melihat receiving di lokasinya sendiri
        if (!$user->isGlobal()) {
            $query->where('lokasi_id', $user->lokasi_id);
        }

        $receivings = $query->latest()->paginate(15);
        return view('admin.receivings.index', compact('receivings'));
    }

    public function create()
    {
        // Cek Hak Akses Create (Gudang atau Dealer)
        if (Auth::user()->can('process-receiving-gudang') || Auth::user()->can('process-receiving-dealer')) {
            // Lanjut
        } else {
            abort(403, 'Anda tidak memiliki akses mencatat penerimaan.');
        }

        $user = Auth::user();

        // Cari PO yang ditujukan ke lokasi user ini dan statusnya APPROVED/PARTIAL
        $query = PurchaseOrder::whereIn('status', ['APPROVED', 'PARTIALLY_RECEIVED']);

        if (!$user->isGlobal()) {
            $query->where('lokasi_id', $user->lokasi_id);
        }

        // Eager load relasi
        $purchaseOrders = $query->with(['supplier', 'sumberLokasi'])
            ->orderBy('tanggal_po', 'desc')
            ->get();

        return view('admin.receivings.create', compact('purchaseOrders'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        
        // Validasi Gate Manual
        if (! ($user->can('process-receiving-gudang') || $user->can('process-receiving-dealer'))) {
            abort(403);
        }

        $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'tanggal_terima'    => 'required|date',
            'items'             => 'required|array|min:1',
            'items.*.qty_terima'=> 'required|integer|min:0',
        ]);

        if (collect($request->items)->sum('qty_terima') <= 0) {
            return back()->with('error', 'Jumlah terima tidak boleh kosong semua.')->withInput();
        }

        DB::beginTransaction();
        try {
            // 1. LOCK PO
            $po = PurchaseOrder::with('details')->lockForUpdate()->findOrFail($request->purchase_order_id);

            // Validasi Lokasi (Prevent Inspect Element)
            if ($user->lokasi_id && $user->lokasi_id != $po->lokasi_id) {
                throw new \Exception("PO ini bukan untuk lokasi Anda.");
            }

            // 2. Tentukan Flow Berdasarkan Tipe Lokasi
            $isDealer = $user->isDealer(); // Helper function di Model User
            
            // Dealer: Skip QC -> Langsung Pending Putaway
            // Gudang: Masuk QC -> Pending QC
            $initialStatus = $isDealer ? 'PENDING_PUTAWAY' : 'PENDING_QC';
            $qcBy = $isDealer ? $user->id : null; 
            $qcAt = $isDealer ? now() : null;

            // 3. Buat Header Receiving
            $receiving = Receiving::create([
                'purchase_order_id' => $po->id,
                'lokasi_id'         => $po->lokasi_id,
                // Generate Nomor: REC/KODE_LOKASI/TGL/SEQ
                'nomor_penerimaan'  => $this->generateReceivingNumber($po->lokasi_id),
                'tanggal_terima'    => $request->tanggal_terima,
                'status'            => $initialStatus,
                'catatan'           => $request->catatan,
                'received_by'       => $user->id,
                'qc_by'             => $qcBy,
                'qc_at'             => $qcAt
            ]);

            foreach ($request->items as $barangId => $itemData) {
                $qtyTerima = (int)$itemData['qty_terima'];
                if ($qtyTerima <= 0) continue;

                $poDetail = $po->details->firstWhere('barang_id', $barangId);

                if (!$poDetail) throw new \Exception("Item ID {$barangId} tidak ada dalam PO ini.");

                // Validasi Over-Receiving
                $sisa = $poDetail->qty_pesan - $poDetail->qty_diterima;
                if ($qtyTerima > $sisa) {
                    throw new \Exception("Over-Receiving pada item {$poDetail->barang_id}. Sisa: $sisa, Input: $qtyTerima");
                }

                // Update PO Detail
                $poDetail->increment('qty_diterima', $qtyTerima);

                // Buat Receiving Detail
                $receiving->details()->create([
                    'barang_id' => $barangId,
                    'qty_terima' => $qtyTerima,
                    'qty_pesan_referensi' => $poDetail->qty_pesan,
                    // Jika Dealer, otomatis lolos QC semua
                    'qty_lolos_qc' => $isDealer ? $qtyTerima : 0, 
                    'qty_gagal_qc' => 0
                ]);
            }

            // Sync Status PO
            $po->syncStatus();

            DB::commit();

            $msg = $isDealer 
                ? 'Penerimaan berhasil. Barang siap disimpan ke Rak (Putaway).' 
                : 'Penerimaan berhasil. Silakan lanjut ke proses QC.';
            
            // Redirect sesuai flow
            $route = $isDealer ? 'admin.putaway.index' : 'admin.qc.index'; // Asumsi route QC ada

            return redirect()->route($route)->with('success', $msg);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error: ' . $e->getMessage())->withInput();
        }
    }

    public function show(Receiving $receiving)
    {
        $this->authorize('view-receiving');
        
        // Security Check Lokasi
        if (!Auth::user()->isGlobal() && Auth::user()->lokasi_id != $receiving->lokasi_id) {
            abort(403);
        }

        $receiving->load(['purchaseOrder.supplier', 'details.barang', 'createdBy', 'receivedBy', 'lokasi']);

        // [MODIFIKASI] Ambil data pergerakan stok yang terjadi akibat penerimaan (Putaway) ini
        $stockMovements = \App\Models\StockMovement::with(['barang', 'rak'])
                            ->where('referensi_type', \App\Models\Receiving::class)
                            ->where('referensi_id', $receiving->id)
                            ->get();

        // Tambahkan $stockMovements ke dalam compact
        return view('admin.receivings.show', compact('receiving', 'stockMovements'));
    }

    // Helper Generate Number
    private function generateReceivingNumber($lokasiId)
    {
        $lokasi = Lokasi::find($lokasiId);
        $kode = $lokasi ? $lokasi->kode_lokasi : 'GEN';
        $date = now()->format('ymd');
        $seq = Receiving::whereDate('created_at', today())->count() + 1;
        return "REC/{$kode}/{$date}/" . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }

    // API Helper untuk Form Create
    public function getPurchaseOrderDetails(PurchaseOrder $purchaseOrder)
    {
        // Pastikan user punya akses
        if (Auth::user()->can('process-receiving-gudang') || Auth::user()->can('process-receiving-dealer')) {
             $details = $purchaseOrder->details()->with('barang')->get()
                ->map(function($d) {
                    $d->sisa = $d->qty_pesan - $d->qty_diterima;
                    return $d;
                })
                ->filter(fn($d) => $d->sisa > 0)
                ->values();
            return response()->json($details);
        }
        abort(403);
    }
}