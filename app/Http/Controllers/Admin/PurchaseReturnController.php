<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseReturn;
use App\Models\Receiving;
use App\Models\ReceivingDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\InventoryBatch;
use App\Models\Rak;
use App\Models\StockMovement;
use App\Models\Lokasi;

class PurchaseReturnController extends Controller
{
    public function index()
    {
        $this->authorize('manage-purchase-returns');
        $returns = PurchaseReturn::with(['supplier', 'receiving'])->latest()->get();
        return view('admin.purchase_returns.index', compact('returns'));
    }

    public function create()
    {
        $this->authorize('manage-purchase-returns');
        // Get receiving documents that have failed items which have not been fully returned yet
        $receivings = Receiving::whereHas('details', function ($query) {
            $query->where('qty_gagal_qc', '>', DB::raw('qty_diretur'));
        })->get();

        return view('admin.purchase_returns.create', compact('receivings'));
    }

    // API Endpoint
    public function getFailedItems(Receiving $receiving)
    {
        $items = $receiving->details()
            ->with('barang')
            ->where('qty_gagal_qc', '>', DB::raw('qty_diretur'))
            ->get();

        return response()->json($items);
    }

    public function store(Request $request)
    {
        $this->authorize('manage-purchase-returns');
        $request->validate([
            'receiving_id' => 'required|exists:receivings,id',
            'tanggal_retur' => 'required|date',
            'catatan' => 'nullable|string|max:255', // Tambahkan validasi catatan
            'items' => 'required|array|min:1',
            'items.*.receiving_detail_id' => 'required|exists:receiving_details,id', // Validasi receiving_detail_id dari form
            'items.*.qty_retur' => 'required|integer|min:1',
            'items.*.alasan' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Eager load relasi yang dibutuhkan
            $receiving = Receiving::with('purchaseOrder', 'lokasi')->findOrFail($request->receiving_id);
            $lokasiId = $receiving->lokasi_id; // Ambil ID lokasi penerimaan

            // --- Cari Rak Karantina ---
            $quarantineRak = Rak::where('lokasi_id', $lokasiId)
                                ->where('tipe_rak', 'KARANTINA')
                                ->first();

            if (!$quarantineRak) {
                throw new \Exception("Rak karantina tidak ditemukan di lokasi " . ($receiving->lokasi->nama_lokasi ?? 'N/A') . ".");
            }
            $quarantineRakId = $quarantineRak->id;
            // --- Akhir Cari Rak Karantina ---

            $return = PurchaseReturn::create([
                'nomor_retur' => $this->generateReturnNumber(),
                'receiving_id' => $receiving->id,
                 // Pastikan relasi purchaseOrder ada sebelum akses supplier_id
                'supplier_id' => $receiving->purchaseOrder->supplier_id ?? null,
                'tanggal_retur' => $request->tanggal_retur,
                'catatan' => $request->catatan,
                'created_by' => Auth::id(),
            ]);

            foreach ($request->items as $itemData) {
                 // Ambil receiving_detail_id dari data item yang disubmit
                 $detailId = $itemData['receiving_detail_id'];
                 // Eager load barang untuk pesan error
                $detail = ReceivingDetail::with('barang')->findOrFail($detailId);
                $barangId = $detail->barang_id;
                $qtyToReturn = (int)$itemData['qty_retur']; // Pastikan integer
                $alasanRetur = $itemData['alasan'];

                // Validasi jumlah retur vs gagal QC
                $availableToReturn = $detail->qty_gagal_qc - $detail->qty_diretur;
                if ($qtyToReturn > $availableToReturn) {
                    throw new \Exception("Jumlah retur ({$qtyToReturn}) untuk barang {$detail->barang->part_name} melebihi jumlah yang tersedia ({$availableToReturn}).");
                }

                // ============================================
                // ++ LOGIKA PENGURANGAN STOK KARANTINA ++
                // ============================================

                // Cari batch di rak karantina berdasarkan part, rak, lokasi
                 $batchKarantina = InventoryBatch::where('barang_id', $barangId)
                                                ->where('rak_id', $quarantineRakId)
                                                ->where('lokasi_id', $lokasiId)
                                                // Opsional: Jika batch karantina dibuat per receiving detail
                                                // Jika demikian, pastikan 'receiving_detail_id' ada di batch saat dibuat di QcController
                                                // ->where('receiving_detail_id', $detailId)
                                                ->where('quantity', '>=', $qtyToReturn) // Pastikan stok cukup
                                                ->orderBy('created_at', 'asc') // Opsi: ambil batch tertua jika ada > 1
                                                ->first();

                if (!$batchKarantina) {
                     // Stok di batch karantina tidak cukup atau tidak ditemukan
                     $currentQuarantineStock = InventoryBatch::where('barang_id', $barangId)
                                                ->where('rak_id', $quarantineRakId)
                                                ->where('lokasi_id', $lokasiId)
                                                ->sum('quantity');
                     throw new \Exception("Stok karantina tidak mencukupi/tidak ditemukan untuk barang {$detail->barang->part_name}. Stok batch: " . ($currentQuarantineStock) . ", Retur: {$qtyToReturn}");
                }

                 // Ambil stok total sebelum dikurangi untuk StockMovement
                 $stokTotalSebelum = InventoryBatch::where('barang_id', $barangId)->where('lokasi_id', $lokasiId)->sum('quantity');

                 // Kurangi stok batch karantina
                 $batchKarantina->decrement('quantity', $qtyToReturn);

                 // Buat Stock Movement
                 StockMovement::create([
                     'barang_id' => $barangId,
                     'lokasi_id' => $lokasiId,
                     'rak_id' => $quarantineRakId, // Rak Sumber adalah Rak Karantina
                     'jumlah' => -$qtyToReturn, // Jumlah negatif karena keluar
                     'stok_sebelum' => $stokTotalSebelum,
                     'stok_sesudah' => $stokTotalSebelum - $qtyToReturn,
                     'referensi_type' => get_class($return), // Referensi ke dokumen retur
                     'referensi_id' => $return->id,
                     'keterangan' => 'Retur Pembelian: ' . ($alasanRetur ?: $return->nomor_retur),
                     'user_id' => Auth::id(),
                 ]);
                // ============================================
                // -- AKHIR LOGIKA PENGURANGAN STOK --
                // ============================================


                // Buat detail return (sudah ada sebelumnya)
                $return->details()->create([
                    'barang_id' => $barangId,
                    'qty_retur' => $qtyToReturn,
                    'alasan' => $alasanRetur,
                    'receiving_detail_id' => $detailId, // Simpan ID detail penerimaan
                ]);

                // Update qty_diretur di receiving detail (sudah ada sebelumnya)
                // Gunakan increment agar aman dari race condition
                $detail->increment('qty_diretur', $qtyToReturn);
            }

            DB::commit();
            // Pesan sukses diperbarui
            return redirect()->route('admin.purchase-returns.index')->with('success', 'Retur pembelian berhasil dibuat dan stok karantina telah dikurangi.');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Purchase Return Store Failed: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString()); // Log lebih detail
            return back()->with('error', 'GAGAL: ' . $e->getMessage())->withInput();
        }
    }

    public function show(PurchaseReturn $purchaseReturn)
    {
        $this->authorize('manage-purchase-returns');
        $purchaseReturn->load(['supplier', 'receiving.purchaseOrder', 'details.barang']);
        return view('admin.purchase_returns.show', compact('purchaseReturn'));
    }

    private function generateReturnNumber()
    {
        $date = now()->format('Ymd');
        $latest = PurchaseReturn::whereDate('created_at', today())->count();
        $sequence = str_pad($latest + 1, 4, '0', STR_PAD_LEFT);
        return "RTN/{$date}/{$sequence}";
    }

private function reduceStockFromBatches($barangId, $rakId, $lokasiId, $quantityToReduce, PurchaseReturn $return, $alasan = null)
     {
         $batches = InventoryBatch::where('barang_id', $barangId)
                                  ->where('rak_id', $rakId)
                                  ->where('lokasi_id', $lokasiId)
                                  ->where('quantity', '>', 0)
                                  ->orderBy('created_at', 'asc') // FIFO for reduction source? Maybe not critical for quarantine.
                                  ->get();

         $remainingQtyToReduce = $quantityToReduce;
         $stokTotalSebelum = InventoryBatch::where('barang_id', $barangId)->where('lokasi_id', $lokasiId)->sum('quantity');


         if ($batches->sum('quantity') < $quantityToReduce) {
             throw new \Exception("Stok karantina tidak cukup. Tersedia: {$batches->sum('quantity')}, Retur: {$quantityToReduce}");
         }

         foreach ($batches as $batch) {
             if ($remainingQtyToReduce <= 0) break;

             $qtyToTake = min($batch->quantity, $remainingQtyToReduce);

             $batch->decrement('quantity', $qtyToTake);

             StockMovement::create([
                 'barang_id' => $barangId,
                 'lokasi_id' => $lokasiId,
                 'rak_id' => $rakId,
                 'jumlah' => -$qtyToTake,
                 'stok_sebelum' => $stokTotalSebelum,
                 'stok_sesudah' => $stokTotalSebelum - $qtyToTake, // Recalculate if needed per batch reduction
                 'referensi_type' => get_class($return),
                 'referensi_id' => $return->id,
                 'keterangan' => 'Retur Pembelian: ' . ($alasan ?: $return->nomor_retur),
                 'user_id' => Auth::id(),
             ]);

             $stokTotalSebelum -= $qtyToTake; // Update for next movement record if needed

             // Opsional: Hapus batch jika quantity jadi 0
             // if ($batch->quantity <= 0) {
             //     $batch->delete();
             // }

             $remainingQtyToReduce -= $qtyToTake;
         }

          if ($remainingQtyToReduce > 0) {
              // Should not happen if initial check passes, but as a safeguard
             throw new \Exception('Gagal mengurangi stok karantina sejumlah yang diminta.');
         }
     }
}
