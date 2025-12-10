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
            // (lokasi_id di tabel PO adalah lokasi tujuan/pemesan)
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

        // --- PERBAIKAN LOGIKA DISINI ---
        // 1. Hitung total qty yang diinput user
        $totalQtyReceived = collect($request->items)->sum('qty_terima');

        // 2. Jika totalnya 0, tolak proses (jangan buat receiving)
        if ($totalQtyReceived <= 0) {
            return back()
                ->with('error', 'Gagal memproses: Anda belum menginput jumlah diterima pada barang manapun (Total Qty 0).')
                ->withInput();
        }
        // --------------------------------

        DB::beginTransaction();
        try {
            $po = PurchaseOrder::with('details')->findOrFail($request->purchase_order_id);

            // Validasi Lokasi
            if ($user->lokasi_id != $po->lokasi_id && !$user->hasRole(['SA', 'PIC'])) {
                return back()->with('error', 'Anda tidak berwenang menerima barang untuk lokasi PO ini.')->withInput();
            }

            $receiving = Receiving::create([
                'purchase_order_id' => $po->id,
                'lokasi_id' => $po->lokasi_id,
                'nomor_penerimaan' => Receiving::generateReceivingNumber(),
                'tanggal_terima' => $request->tanggal_terima,
                'status' => 'PENDING_QC', 
                'catatan' => $request->catatan,
                'received_by' => $user->id,
            ]);

            foreach ($request->items as $barangId => $itemData) {
                $qtyTerima = (int)$itemData['qty_terima'];
                
                // Hanya proses item yang ada isinya (> 0)
                if ($qtyTerima > 0) {
                    $poDetail = $po->details->firstWhere('barang_id', $barangId);

                    if ($poDetail) {
                        $poDetail->increment('qty_diterima', $qtyTerima);
                    }

                    $receiving->details()->create([
                        'barang_id' => $barangId,
                        'qty_terima' => $qtyTerima,
                        'qty_pesan_referensi' => $poDetail ? $poDetail->qty_pesan : 0
                    ]);
                }
            }

            $po->refresh();
            
            // Cek status PO
            $fullyReceived = $po->details->every(fn($detail) => $detail->qty_diterima >= $detail->qty_pesan);
            $po->status = $fullyReceived ? 'FULLY_RECEIVED' : 'PARTIALLY_RECEIVED';
            $po->save();

            DB::commit();
            return redirect()->route('admin.receivings.index')->with('success', 'Data penerimaan barang berhasil disimpan. Silakan lanjut ke proses QC.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage())->withInput();
        }
    }

    public function show(Receiving $receiving)
    {
        // PERUBAHAN: Load relasi barang, bukan part
        $receiving->load([
            'purchaseOrder.supplier',
            'purchaseOrder.sumberLokasi', // Load sumber internal
            'details.barang',
            'createdBy',
            'receivedBy',
            'qcBy',
            'putawayBy',
            'lokasi'
        ]);

        // Load history pergerakan stok
        $stockMovements = $receiving->stockMovements()->with(['rak', 'user', 'barang'])->get();

        return view('admin.receivings.show', compact('receiving', 'stockMovements'));
    }

    public function getPurchaseOrderDetails(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('perform-warehouse-ops');

        // PERUBAHAN: Load relasi barang
        $purchaseOrder->load('details.barang');

        // Filter: Hanya item yang belum lunas qty-nya
        $itemsToReceive = $purchaseOrder->details->filter(function ($detail) {
            return $detail->qty_pesan > $detail->qty_diterima;
        });

        return response()->json($itemsToReceive->values());
    }
}
