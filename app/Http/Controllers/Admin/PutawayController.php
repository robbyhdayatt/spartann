<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryBatch;
use App\Models\Lokasi; // DIUBAH
use App\Models\Part;
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

        $receiving->load('details.part');

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
                $detail = ReceivingDetail::with('part')->findOrFail($detailId);
                $part = $detail->part;
                $jumlahMasuk = $detail->qty_lolos_qc;

                if ($jumlahMasuk <= 0) continue;

                // Membuat Inventory Batch untuk FIFO
                InventoryBatch::create([
                    'part_id'             => $detail->part_id,
                    'rak_id'              => $data['rak_id'],
                    'lokasi_id'           => $receiving->lokasi_id,
                    'receiving_detail_id' => $detail->id,
                    'quantity'            => $jumlahMasuk,
                ]);

                // Kalkulasi harga rata-rata
                $poDetail = PurchaseOrderDetail::where('purchase_order_id', $receiving->purchase_order_id)
                                               ->where('part_id', $part->id)
                                               ->first();
                $hargaBeliBaru = $poDetail ? $poDetail->harga_beli : $part->harga_beli_default;

                // Stok total sebelum batch ini ditambahkan
                $stokLamaTotal = $part->inventoryBatches()->sum('quantity') - $jumlahMasuk;

                $hargaRataRataLama = $part->harga_beli_rata_rata;
                $totalNilaiLama = $stokLamaTotal * $hargaRataRataLama;
                $totalNilaiBaru = $jumlahMasuk * $hargaBeliBaru;
                $totalStokBaru = $stokLamaTotal + $jumlahMasuk;

                $hargaRataRataBaru = ($totalStokBaru > 0) ? (($totalNilaiLama + $totalNilaiBaru) / $totalStokBaru) : $hargaBeliBaru;

                $part->update(['harga_beli_rata_rata' => $hargaRataRataBaru]);

                // Catat di Stock Movement
                $receiving->stockMovements()->create([
                    'part_id'      => $detail->part_id,
                    'lokasi_id'    => $receiving->lokasi_id,
                    'rak_id'       => $data['rak_id'],
                    'jumlah'       => $jumlahMasuk,
                    'stok_sebelum' => $stokLamaTotal,
                    'stok_sesudah' => $totalStokBaru,
                    'user_id'      => Auth::id(),
                    'keterangan'   => 'Stok masuk dari PO ' . $receiving->purchaseOrder->nomor_po,
                ]);

                $detail->update(['qty_disimpan' => $jumlahMasuk]);
            }

            $receiving->status = 'COMPLETED';
            $receiving->putaway_by = Auth::id();
            $receiving->putaway_at = now();
            $receiving->save();

            DB::commit();
            return redirect()->route('admin.putaway.index')->with('success', 'Barang berhasil disimpan, stok dan harga rata-rata telah diperbarui.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage())->withInput();
        }
    }
}
