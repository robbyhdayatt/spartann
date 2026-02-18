<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryBatch;
use App\Models\Rak;
use App\Models\Barang;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class QuarantineStockController extends Controller
{
    public function index()
    {
        $this->authorize('view-quarantine-stock');
        
        $user = Auth::user();
        $lokasiFilterId = null;

        if (!$user->hasRole(['SA', 'PIC'])) {
            $lokasiFilterId = $user->lokasi_id;
        }

        // 1. Ambil Stok Fisik di Rak Karantina
        $quarantineQuery = InventoryBatch::whereHas('rak', function ($query) {
            $query->where('tipe_rak', 'KARANTINA');
        })->where('quantity', '>', 0);

        if ($lokasiFilterId) {
            $quarantineQuery->where('lokasi_id', $lokasiFilterId);
        }

        $quarantineItems = $quarantineQuery
            ->select('barang_id', 'rak_id', 'lokasi_id', DB::raw('SUM(quantity) as total_quantity'))
            ->groupBy('barang_id', 'rak_id', 'lokasi_id')
            ->with(['barang', 'rak', 'lokasi'])
            ->get();

        // 2. MODIFIKASI POIN 1: Hitung Stok Pending (Sedang diajukan Write-Off tapi belum approve)
        foreach ($quarantineItems as $item) {
            $pendingQty = StockAdjustment::where('barang_id', $item->barang_id)
                ->where('lokasi_id', $item->lokasi_id)
                ->where('rak_id', $item->rak_id) // Pastikan pending di rak yang sama
                ->where('status', 'PENDING_APPROVAL')
                ->where('tipe', 'KURANG') // Write-off pasti pengurangan
                ->sum('jumlah');

            $item->pending_quantity = $pendingQty;
            $item->available_quantity = $item->total_quantity - $pendingQty;
        }

        // Ambil data rak penyimpanan untuk dropdown modal
        $storageRaksQuery = Rak::where('tipe_rak', 'PENYIMPANAN')->where('is_active', true);
        if ($lokasiFilterId) {
            $storageRaksQuery->where('lokasi_id', $lokasiFilterId);
        }
        
        $storageRaks = $storageRaksQuery->orderBy('kode_rak')->get()->groupBy('lokasi_id');

        return view('admin.quarantine_stock.index', compact('quarantineItems', 'storageRaks'));
    }

    public function process(Request $request)
    {
        $this->authorize('manage-quarantine-stock');

        $validated = $request->validate([
            'barang_id' => 'required|exists:barangs,id',
            'rak_id'    => 'required|exists:raks,id',
            'lokasi_id' => 'required|exists:lokasi,id',
            'action'    => 'required|in:return_to_stock,write_off',
            'quantity'  => 'required|integer|min:1',
            'destination_rak_id' => 'nullable|required_if:action,return_to_stock|exists:raks,id',
            'reason'    => 'nullable|required_if:action,write_off|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            // A. Cek Stok Fisik (Existing Logic)
            $batches = InventoryBatch::where('barang_id', $validated['barang_id'])
                ->where('rak_id', $validated['rak_id'])
                ->where('lokasi_id', $validated['lokasi_id'])
                ->where('quantity', '>', 0)
                ->orderBy('created_at', 'asc')
                ->lockForUpdate() 
                ->get();

            $totalPhysical = $batches->sum('quantity');

            // B. MODIFIKASI POIN 1: Cek Stok Pending lagi (Double Check saat submit)
            $pendingQty = StockAdjustment::where('barang_id', $validated['barang_id'])
                ->where('lokasi_id', $validated['lokasi_id'])
                ->where('rak_id', $validated['rak_id'])
                ->where('status', 'PENDING_APPROVAL')
                ->lockForUpdate()
                ->sum('jumlah');

            $availableQty = $totalPhysical - $pendingQty;

            if ($validated['quantity'] > $availableQty) {
                throw new \Exception("Gagal! Sebagian stok sedang menunggu persetujuan Write-Off. Stok tersedia: {$availableQty} unit.");
            }

            // --- PROSES AKSI ---

            if ($validated['action'] === 'return_to_stock') {
                $destRak = Rak::findOrFail($validated['destination_rak_id']);
                
                if ($validated['lokasi_id'] != $destRak->lokasi_id) {
                    throw new \Exception('Rak tujuan harus berada di lokasi yang sama.');
                }

                // MODIFIKASI POIN 2: Transfer dengan Backdating (Clone Tanggal)
                $this->transferWithBackdating(
                    $batches,
                    $validated['quantity'],
                    $validated['barang_id'],
                    $validated['lokasi_id'],
                    $validated['rak_id'],       // Dari Rak Karantina
                    $destRak->id,               // Ke Rak Penyimpanan
                    "Restock dari Karantina"
                );

                $msg = 'Barang berhasil dikembalikan ke stok penjualan (Batch date dipertahankan).';

            } elseif ($validated['action'] === 'write_off') {
                // Buat Adjustment Request (Pending Approval)
                StockAdjustment::create([
                    'barang_id'  => $validated['barang_id'],
                    'lokasi_id'  => $validated['lokasi_id'],
                    'rak_id'     => $validated['rak_id'],
                    'tipe'       => 'KURANG',
                    'jumlah'     => $validated['quantity'],
                    'alasan'     => "[Write-Off Karantina] " . $validated['reason'],
                    'status'     => 'PENDING_APPROVAL', // Status Pending
                    'created_by' => Auth::id(),
                ]);

                $msg = 'Pengajuan Write-Off berhasil dibuat. Stok ditahan menunggu persetujuan.';
            }

            DB::commit();
            return redirect()->route('admin.quarantine-stock.index')->with('success', $msg);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * MODIFIKASI POIN 2: Transfer Stok dengan Mempertahankan Tanggal Batch (Backdating)
     * Agar logika FIFO (First In First Out) tetap akurat.
     */
    private function transferWithBackdating($sourceBatches, $qtyNeeded, $barangId, $lokasiId, $sourceRakId, $destRakId, $keterangan)
    {
        $sisaButuh = $qtyNeeded;

        foreach ($sourceBatches as $batch) {
            if ($sisaButuh <= 0) break;

            $ambil = min($batch->quantity, $sisaButuh);
            
            // 1. Kurangi Batch Karantina
            $stokAwalSumber = $batch->quantity;
            $batch->decrement('quantity', $ambil);
            
            // Log Keluar
            StockMovement::create([
                'barang_id' => $barangId, 'lokasi_id' => $lokasiId, 'rak_id' => $sourceRakId,
                'jumlah' => -$ambil,
                'stok_sebelum' => $stokAwalSumber, 'stok_sesudah' => $stokAwalSumber - $ambil,
                'referensi_type' => 'App\Models\InventoryBatch', 'referensi_id' => $batch->id,
                'keterangan' => $keterangan . " (OUT)",
                'user_id' => Auth::id(),
                'created_at' => now() 
            ]);

            // 2. Buat Batch Baru di Rak Tujuan TAPI PAKAI TANGGAL LAMA ($batch->created_at)
            // Ini inti dari solusi Backdating FIFO
            $newBatch = InventoryBatch::create([
                'barang_id' => $barangId,
                'rak_id'    => $destRakId,
                'lokasi_id' => $lokasiId,
                'quantity'  => $ambil,
                'receiving_detail_id' => $batch->receiving_detail_id, // Copy referensi receiving asal jika ada
                'created_at' => $batch->created_at, // <--- COPY TIMESTAMP LAMA
                'updated_at' => now()
            ]);

            // Hitung snapshot stok rak tujuan untuk log
            // (Query ini agak berat di loop, tapi perlu untuk akurasi kartu stok)
            $stokRakTujuanSebelum = InventoryBatch::where('barang_id', $barangId)
                ->where('rak_id', $destRakId)->where('id', '!=', $newBatch->id)->sum('quantity');

            // Log Masuk
            StockMovement::create([
                'barang_id' => $barangId, 'lokasi_id' => $lokasiId, 'rak_id' => $destRakId,
                'jumlah' => $ambil,
                'stok_sebelum' => $stokRakTujuanSebelum, 'stok_sesudah' => $stokRakTujuanSebelum + $ambil,
                'referensi_type' => 'App\Models\InventoryBatch', 'referensi_id' => $newBatch->id,
                'keterangan' => $keterangan . " (IN - Backdated)",
                'user_id' => Auth::id(),
                'created_at' => now()
            ]);

            $sisaButuh -= $ambil;
        }
    }
}