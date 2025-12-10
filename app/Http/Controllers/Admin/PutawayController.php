<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryBatch;
use App\Models\Lokasi; 
use App\Models\Barang;
use App\Models\PurchaseOrderDetail;
use App\Models\Rak;
use App\Models\Receiving;
use App\Models\ReceivingDetail;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PutawayController extends Controller
{
    public function index()
    {
        $this->authorize('perform-warehouse-ops');
        $user = Auth::user();

        $query = Receiving::where('status', 'PENDING_PUTAWAY')
                            ->with(['purchaseOrder.supplier', 'lokasi']); 

        if (!$user->hasRole(['SA', 'PIC', 'MA'])) {
            $query->where('lokasi_id', $user->lokasi_id);
        }

        $receivings = $query->latest('qc_at')->paginate(15);

        return view('admin.putaway.index', compact('receivings'));
    }

    public function showPutawayForm(Receiving $receiving)
    {
        $this->authorize('perform-warehouse-ops');
        if ($receiving->status !== 'PENDING_PUTAWAY') {
            return redirect()->route('admin.putaway.index')->with('error', 'Penerimaan ini tidak siap untuk proses Putaway.');
        }

        if (Auth::user()->lokasi_id != $receiving->lokasi_id && !Auth::user()->hasRole(['SA', 'PIC'])) {
            return redirect()->route('admin.putaway.index')->with('error', 'Anda tidak berwenang memproses putaway untuk lokasi ini.');
        }

        $receiving->load('details.barang');

        $raks = Rak::where('lokasi_id', $receiving->lokasi_id)
                    ->where('is_active', true)
                    ->where('tipe_rak', 'PENYIMPANAN')
                    ->orderBy('kode_rak')
                    ->get();

        $itemsToPutaway = $receiving->details()->where('qty_lolos_qc', '>', 0)->get();

        return view('admin.putaway.form', compact('receiving', 'itemsToPutaway', 'raks'));
    }

    public function storePutaway(Request $request, Receiving $receiving)
    {
        $this->authorize('perform-warehouse-ops');
        $request->validate([
            'items' => 'required|array',
            'items.*.rak_id' => 'required|exists:raks,id',
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->items as $detailId => $data) {
                // Jangan eager load 'barang' disini karena kita akan lock manual
                $detail = ReceivingDetail::findOrFail($detailId);
                
                // [PERBAIKAN RACE CONDITION]
                // Lock master Barang untuk memastikan perhitungan rata-rata harga beli (Selling In) atomic
                $barang = Barang::where('id', $detail->barang_id)->lockForUpdate()->first();
                
                $jumlahMasuk = $detail->qty_lolos_qc;

                if ($jumlahMasuk <= 0) continue;

                // 1. Buat Inventory Batch (FIFO)
                InventoryBatch::create([
                    'barang_id'           => $detail->barang_id, 
                    'rak_id'              => $data['rak_id'],
                    'lokasi_id'           => $receiving->lokasi_id,
                    'receiving_detail_id' => $detail->id,
                    'quantity'            => $jumlahMasuk,
                ]);

                // 2. Kalkulasi Harga Rata-rata (Weighted Average Cost) untuk 'selling_in'
                $poDetail = PurchaseOrderDetail::where('purchase_order_id', $receiving->purchase_order_id)
                                               ->where('barang_id', $barang->id)
                                               ->first();

                $hargaBeliBaru = $poDetail ? $poDetail->harga_beli : $barang->selling_in;

                // Hitung total stok Termasuk yang baru masuk (karena masih dalam 1 transaksi)
                $allBatches = InventoryBatch::where('barang_id', $barang->id)->get(); // Tidak perlu lock lagi, karena master barang sudah dilock
                $totalStokSekarang = $allBatches->sum('quantity'); 
                $stokLama = $totalStokSekarang - $jumlahMasuk;

                $nilaiAsetLama = $stokLama * $barang->selling_in;
                $nilaiAsetBaru = $jumlahMasuk * $hargaBeliBaru;

                $sellingInBaru = ($totalStokSekarang > 0)
                    ? (($nilaiAsetLama + $nilaiAsetBaru) / $totalStokSekarang)
                    : $hargaBeliBaru;

                // Update master Barang
                $barang->update(['selling_in' => $sellingInBaru]);

                // 3. Catat Stock Movement
                $receiving->stockMovements()->create([
                    'barang_id'    => $detail->barang_id,
                    'lokasi_id'    => $receiving->lokasi_id,
                    'rak_id'       => $data['rak_id'],
                    'jumlah'       => $jumlahMasuk,
                    'stok_sebelum' => $stokLama,
                    'stok_sesudah' => $totalStokSekarang,
                    'user_id'      => Auth::id(),
                    'keterangan'   => 'Putaway dari PO ' . ($receiving->purchaseOrder->nomor_po ?? '-'),
                ]);

                $detail->update(['qty_disimpan' => $jumlahMasuk]);
            }

            $receiving->status = 'COMPLETED';
            $receiving->putaway_by = Auth::id();
            $receiving->putaway_at = now();
            $receiving->save();

            DB::commit();
            return redirect()->route('admin.putaway.index')->with('success', 'Putaway berhasil. Stok dan Selling In diperbarui.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error: ' . $e->getMessage())->withInput();
        }
    }
}