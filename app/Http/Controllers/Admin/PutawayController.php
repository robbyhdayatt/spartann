<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryBatch;
use App\Models\Lokasi; // DIUBAH
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
                            ->with(['purchaseOrder.supplier', 'lokasi']); // DIUBAH

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

        // Otorisasi tambahan untuk memastikan user berada di lokasi yang benar
        if (Auth::user()->lokasi_id != $receiving->lokasi_id && !Auth::user()->hasRole(['SA', 'PIC'])) {
            return redirect()->route('admin.putaway.index')->with('error', 'Anda tidak berwenang memproses putaway untuk lokasi ini.');
        }

        $receiving->load('details.barang');

        // Mengambil rak berdasarkan lokasi_id dari dokumen penerimaan
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
                // Eager load 'barang'
                $detail = ReceivingDetail::with('barang')->findOrFail($detailId);
                $barang = $detail->barang;
                $jumlahMasuk = $detail->qty_lolos_qc;

                if ($jumlahMasuk <= 0) continue;

                // 1. Buat Inventory Batch (FIFO)
                InventoryBatch::create([
                    'barang_id'           => $detail->barang_id, // Ganti part_id
                    'rak_id'              => $data['rak_id'],
                    'lokasi_id'           => $receiving->lokasi_id,
                    'receiving_detail_id' => $detail->id,
                    'quantity'            => $jumlahMasuk,
                ]);

                // 2. Kalkulasi Harga Rata-rata (Weighted Average Cost) untuk 'selling_in'
                // Ambil harga beli dari PO
                $poDetail = PurchaseOrderDetail::where('purchase_order_id', $receiving->purchase_order_id)
                                               ->where('barang_id', $barang->id)
                                               ->first();

                // Jika tidak ada di PO (misal receiving manual), pakai selling_in lama
                $hargaBeliBaru = $poDetail ? $poDetail->harga_beli : $barang->selling_in;

                // Hitung total stok dan nilai aset LAMA (sebelum barang ini masuk)
                // Kita pakai query manual agar tidak ikut menghitung batch yang baru saja dibuat di atas (jika ada race condition)
                // Tapi karena ini dalam transaksi, batch di atas sudah terhitung.
                // Cara aman:
                $allBatches = $barang->inventoryBatches;
                $totalStokSekarang = $allBatches->sum('quantity'); // Termasuk yang baru masuk
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
