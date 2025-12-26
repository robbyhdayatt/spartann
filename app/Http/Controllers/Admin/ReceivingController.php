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
        /** @var \App\Models\User $user */
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
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $this->authorize('perform-warehouse-ops');

        // Query Dasar: PO yang sudah diapprove/partial
        $query = PurchaseOrder::whereIn('status', ['APPROVED', 'PARTIALLY_RECEIVED']);

        if ($user->hasRole(['SA', 'PIC', 'MA'])) {
            // Super admin bisa lihat semua
        } else {
            // Dealer/Pusat hanya bisa melihat PO yang TUJUANNYA adalah lokasi mereka
            $query->where('lokasi_id', $user->lokasi_id);
        }

        $purchaseOrders = $query->with(['supplier', 'sumberLokasi'])
                                ->orderBy('tanggal_po', 'desc')
                                ->get();

        return view('admin.receivings.create', compact('purchaseOrders'));
    }

    public function store(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $this->authorize('perform-warehouse-ops');

        $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'tanggal_terima' => 'required|date',
            'catatan' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.barang_id' => 'required|exists:barangs,id',
            'items.*.qty_terima' => 'required|integer|min:0',
        ]);

        $totalQtyReceived = collect($request->items)->sum('qty_terima');

        if ($totalQtyReceived <= 0) {
            return back()
                ->with('error', 'Gagal memproses: Anda belum menginput jumlah diterima pada barang manapun (Total Qty 0).')
                ->withInput();
        }

        DB::beginTransaction();
        try {
            $po = PurchaseOrder::with('details')->findOrFail($request->purchase_order_id);

            // Validasi Lokasi
            if ($user->lokasi_id != $po->lokasi_id && !$user->hasRole(['SA', 'PIC'])) {
                return back()->with('error', 'Anda tidak berwenang menerima barang untuk lokasi PO ini.')->withInput();
            }

            // --- LOGIKA BYPASS QC UNTUK DEALER ---
            // Cek apakah user adalah staff dealer (bukan pusat)
            $isDealer = $user->hasRole(['KC', 'PC', 'AD', 'KSR', 'SLS']); // Gunakan definisi dealer staff yang konsisten
            
            // Jika Dealer, status langsung ke PUTAWAY. Jika Pusat, masuk ke QC dulu.
            $initialStatus = $isDealer ? 'PENDING_PUTAWAY' : 'PENDING_QC';
            
            // Jika Bypass QC, anggap user ini juga yang melakukan QC secara otomatis
            $qcBy = $isDealer ? $user->id : null;
            $qcAt = $isDealer ? now() : null;

            $receiving = Receiving::create([
                'purchase_order_id' => $po->id,
                'lokasi_id' => $po->lokasi_id,
                'nomor_penerimaan' => Receiving::generateReceivingNumber(),
                'tanggal_terima' => $request->tanggal_terima,
                'status' => $initialStatus, 
                'catatan' => $request->catatan,
                'received_by' => $user->id,
                // Isi kolom QC jika bypass
                'qc_by' => $qcBy,
                'qc_at' => $qcAt,
            ]);

            foreach ($request->items as $barangId => $itemData) {
                $qtyTerima = (int)$itemData['qty_terima'];
                
                if ($qtyTerima > 0) {
                    $poDetail = $po->details->firstWhere('barang_id', $barangId);

                    if ($poDetail) {
                        $poDetail->increment('qty_diterima', $qtyTerima);
                    }
                    
                    // --- AUTO FILL QTY QC JIKA DEALER ---
                    $qtyLolos = $isDealer ? $qtyTerima : 0;
                    $qtyGagal = 0; // Dealer diasumsikan tidak ada retur/gagal QC di tahap ini (langsung terima)

                    $receiving->details()->create([
                        'barang_id' => $barangId,
                        'qty_terima' => $qtyTerima,
                        'qty_pesan_referensi' => $poDetail ? $poDetail->qty_pesan : 0,
                        // Isi hasil QC otomatis
                        'qty_lolos_qc' => $qtyLolos,
                        'qty_gagal_qc' => $qtyGagal
                    ]);
                }
            }

            $po->refresh();
            
            // Cek status PO
            $fullyReceived = $po->details->every(fn($detail) => $detail->qty_diterima >= $detail->qty_pesan);
            $po->status = $fullyReceived ? 'FULLY_RECEIVED' : 'PARTIALLY_RECEIVED';
            $po->save();

            DB::commit();

            // Pesan sukses berbeda tergantung role
            if ($isDealer) {
                return redirect()->route('admin.putaway.index')->with('success', 'Barang diterima. QC dilewati (Auto Pass). Silakan lanjut ke proses Putaway.');
            } else {
                return redirect()->route('admin.receivings.index')->with('success', 'Data penerimaan barang berhasil disimpan. Silakan lanjut ke proses QC.');
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage())->withInput();
        }
    }

    public function show(Receiving $receiving)
    {
        $receiving->load([
            'purchaseOrder.supplier',
            'purchaseOrder.sumberLokasi', 
            'details.barang',
            'createdBy',
            'receivedBy',
            'qcBy',
            'putawayBy',
            'lokasi'
        ]);

        $stockMovements = $receiving->stockMovements()->with(['rak', 'user', 'barang'])->get();

        return view('admin.receivings.show', compact('receiving', 'stockMovements'));
    }

    public function getPurchaseOrderDetails(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('perform-warehouse-ops');

        $purchaseOrder->load('details.barang');

        $itemsToReceive = $purchaseOrder->details->filter(function ($detail) {
            return $detail->qty_pesan > $detail->qty_diterima;
        });

        return response()->json($itemsToReceive->values());
    }
}