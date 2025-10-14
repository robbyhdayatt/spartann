<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lokasi; // DIUBAH
use App\Models\Receiving;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReceivingController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        // PERUBAHAN: Menggunakan relasi 'lokasi'
        $query = Receiving::with(['purchaseOrder', 'lokasi', 'receivedBy']);

        // Filter data berdasarkan lokasi pengguna, kecuali untuk Super Admin dan PIC
        if (!$user->hasRole(['SA', 'PIC', 'MA'])) {
            $query->where('gudang_id', $user->gudang_id);
        }

        $receivings = $query->latest()->paginate(15);
        return view('admin.receivings.index', compact('receivings'));
    }

    public function create()
    {
        // PERUBAHAN: Menggunakan gate baru 'perform-warehouse-ops'
        $this->authorize('perform-warehouse-ops');
        
        $user = Auth::user();
        
        // Hanya Admin Gudang di Gudang Pusat yang bisa melakukan penerimaan dari PO
        if ($user->lokasi && $user->lokasi->tipe === 'PUSAT') {
            $query = PurchaseOrder::whereIn('status', ['APPROVED', 'PARTIALLY_RECEIVED']);
            $query->where('gudang_id', $user->gudang_id); // Filter PO untuk lokasinya saja
            $purchaseOrders = $query->orderBy('tanggal_po', 'desc')->get();
        } else {
            // Jika user bukan di Gudang Pusat, tidak ada PO yang bisa diterima
            $purchaseOrders = collect(); 
        }

        return view('admin.receivings.create', compact('purchaseOrders'));
    }

    public function store(Request $request)
    {
        $this->authorize('perform-warehouse-ops');

        $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'tanggal_terima' => 'required|date',
            'catatan' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.part_id' => 'required|exists:parts,id',
            'items.*.qty_terima' => 'required|integer|min:0',
        ]);

        DB::beginTransaction();
        try {
            $po = PurchaseOrder::with('details')->findOrFail($request->purchase_order_id);

            // Pastikan hanya user dari lokasi yang sama dengan PO yang bisa membuat receiving
            if (Auth::user()->gudang_id != $po->gudang_id) {
                return back()->with('error', 'Anda tidak berwenang menerima barang untuk lokasi ini.')->withInput();
            }

            $receiving = Receiving::create([
                'purchase_order_id' => $po->id,
                'gudang_id' => $po->gudang_id,
                'nomor_penerimaan' => Receiving::generateReceivingNumber(),
                'tanggal_terima' => $request->tanggal_terima,
                'status' => 'PENDING_QC', // Asumsi alur QC masih ada
                'catatan' => $request->catatan,
                'received_by' => Auth::id(),
            ]);

            foreach ($request->items as $partId => $itemData) {
                $qtyTerima = (int)$itemData['qty_terima'];
                if ($qtyTerima > 0) { // Hanya proses jika ada barang yang diterima
                    $poDetail = $po->details->firstWhere('part_id', $partId);
                    if ($poDetail) {
                        $poDetail->qty_diterima += $qtyTerima;
                        $poDetail->save();
                    }
                    $receiving->details()->create([
                        'part_id' => $partId,
                        'qty_terima' => $qtyTerima,
                    ]);
                }
            }

            $po->refresh();
            $fullyReceived = $po->details->every(fn($detail) => $detail->qty_diterima >= $detail->qty_pesan);

            $po->status = $fullyReceived ? 'FULLY_RECEIVED' : 'PARTIALLY_RECEIVED';
            $po->save();

            DB::commit();
            return redirect()->route('admin.receivings.index')->with('success', 'Data penerimaan barang berhasil disimpan.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage())->withInput();
        }
    }

    public function show(Receiving $receiving)
    {
        $receiving->load('purchaseOrder.supplier', 'details.part', 'createdBy', 'receivedBy', 'qcBy', 'putawayBy', 'lokasi');

        $stockMovements = $receiving->stockMovements()->with(['rak', 'user'])->get();

        return view('admin.receivings.show', compact('receiving', 'stockMovements'));
    }
    
    // Fungsi getPoDetails tidak perlu diubah karena sudah benar
    public function getPoDetails(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load('details.part');
        return response()->json($purchaseOrder);
    }
}