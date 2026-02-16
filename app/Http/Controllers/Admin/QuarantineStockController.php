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
        
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $lokasiFilterId = null;

        if (!$user->hasRole(['SA', 'PIC'])) {
            $lokasiFilterId = $user->lokasi_id;
        }

        // Ambil stok karantina (quantity > 0)
        $quarantineQuery = InventoryBatch::whereHas('rak', function ($query) {
            $query->where('tipe_rak', 'KARANTINA');
        })->where('quantity', '>', 0);

        if ($lokasiFilterId) {
            $quarantineQuery->where('lokasi_id', $lokasiFilterId);
        }

        // Group by Barang & Lokasi & Rak untuk tampilan ringkas
        $quarantineItems = $quarantineQuery
            ->select('barang_id', 'rak_id', 'lokasi_id', DB::raw('SUM(quantity) as total_quantity'))
            ->groupBy('barang_id', 'rak_id', 'lokasi_id')
            ->with(['barang', 'rak', 'lokasi'])
            ->get();

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
            'rak_id'    => 'required|exists:raks,id', // Rak Asal (Karantina)
            'lokasi_id' => 'required|exists:lokasi,id',
            'action'    => 'required|in:return_to_stock,write_off',
            'quantity'  => 'required|integer|min:1',
            'destination_rak_id' => 'nullable|required_if:action,return_to_stock|exists:raks,id',
            'reason'    => 'nullable|required_if:action,write_off|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            // 1. Ambil Batch Karantina dengan Locking (FIFO)
            // lockForUpdate() MENCEGAH RACE CONDITION
            $batches = InventoryBatch::where('barang_id', $validated['barang_id'])
                ->where('rak_id', $validated['rak_id'])
                ->where('lokasi_id', $validated['lokasi_id'])
                ->where('quantity', '>', 0)
                ->orderBy('created_at', 'asc') // FIFO
                ->lockForUpdate() 
                ->get();

            $totalAvailable = $batches->sum('quantity');

            if ($validated['quantity'] > $totalAvailable) {
                throw new \Exception("Stok tidak mencukupi atau sedang diproses user lain. (Tersedia: {$totalAvailable})");
            }

            // Logic berdasarkan Aksi
            if ($validated['action'] === 'return_to_stock') {
                $destRak = Rak::findOrFail($validated['destination_rak_id']);
                
                if ($validated['lokasi_id'] != $destRak->lokasi_id) {
                    throw new \Exception('Rak tujuan harus berada di lokasi yang sama.');
                }

                // A. Kurangi Stok Karantina (FIFO)
                $this->processFifoStockReduction(
                    $batches, 
                    $validated['quantity'], 
                    $validated['barang_id'], 
                    $validated['lokasi_id'], 
                    $validated['rak_id'], // Rak Asal
                    "Pindah dari Karantina ke {$destRak->kode_rak}"
                );

                // B. Tambah Stok ke Rak Tujuan (Sales)
                $this->addStockToSalesRak(
                    $validated['barang_id'],
                    $destRak->id,
                    $validated['lokasi_id'],
                    $validated['quantity'],
                    "Terima dari Karantina"
                );

                $msg = 'Barang berhasil dikembalikan ke stok penjualan.';

            } elseif ($validated['action'] === 'write_off') {
                // Untuk Write Off, kita buat Adjustment Request (Pending Approval)
                // Kita TIDAK langsung potong stok di sini, tapi tunggu approval Adjustment.
                // ATAU: Bisa langsung potong jika user punya hak akses.
                // Sesuai kode Anda sebelumnya -> Buat StockAdjustment.
                
                StockAdjustment::create([
                    'barang_id'  => $validated['barang_id'],
                    'lokasi_id'  => $validated['lokasi_id'],
                    'rak_id'     => $validated['rak_id'],
                    'tipe'       => 'KURANG',
                    'jumlah'     => $validated['quantity'],
                    'alasan'     => "[Write-Off Karantina] " . $validated['reason'],
                    'status'     => 'PENDING_APPROVAL',
                    'created_by' => Auth::id(),
                ]);

                $msg = 'Pengajuan Write-Off berhasil dibuat. Menunggu persetujuan.';
            }

            DB::commit();
            return redirect()->route('admin.quarantine-stock.index')->with('success', $msg);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Mengurangi stok dari kumpulan batch secara FIFO
     */
    private function processFifoStockReduction($batches, $qtyNeeded, $barangId, $lokasiId, $rakId, $keterangan)
    {
        $sisa = $qtyNeeded;

        foreach ($batches as $batch) {
            if ($sisa <= 0) break;

            $ambil = min($batch->quantity, $sisa);
            
            // Catat Log Stok (Movement)
            $stokAwal = $batch->quantity;
            $stokAkhir = $stokAwal - $ambil;

            $batch->decrement('quantity', $ambil);
            
            StockMovement::create([
                'barang_id' => $barangId,
                'lokasi_id' => $lokasiId,
                'rak_id'    => $rakId,
                'jumlah'    => -$ambil,
                'stok_sebelum' => $stokAwal,
                'stok_sesudah' => $stokAkhir,
                'referensi_type' => 'App\Models\User', // Atau tipe lain yang relevan
                'referensi_id'   => Auth::id(),
                'keterangan'     => $keterangan,
                'user_id'        => Auth::id(),
                'created_at'     => now(),
            ]);

            // Hapus batch kosong untuk kebersihan database (opsional)
            // if ($batch->quantity == 0) $batch->delete();

            $sisa -= $ambil;
        }
    }

    /**
     * Menambah stok ke Rak Penjualan (Membuat Batch Baru)
     */
    private function addStockToSalesRak($barangId, $rakId, $lokasiId, $qty, $keterangan)
    {
        // 1. Snapshot stok sebelumnya di rak tujuan (untuk log)
        // Kita hitung manual karena batch baru stok awalnya 0
        $stokRakSebelum = InventoryBatch::where('barang_id', $barangId)
            ->where('rak_id', $rakId)
            ->where('lokasi_id', $lokasiId)
            ->sum('quantity');

        // 2. Buat Batch Baru
        InventoryBatch::create([
            'barang_id' => $barangId,
            'rak_id'    => $rakId,
            'lokasi_id' => $lokasiId,
            'quantity'  => $qty,
            'receiving_detail_id' => null // Barang pindahan, bukan receiving baru
        ]);

        // 3. Catat Log Stok
        StockMovement::create([
            'barang_id' => $barangId,
            'lokasi_id' => $lokasiId,
            'rak_id'    => $rakId,
            'jumlah'    => $qty,
            'stok_sebelum' => $stokRakSebelum,
            'stok_sesudah' => $stokRakSebelum + $qty,
            'referensi_type' => 'App\Models\User',
            'referensi_id'   => Auth::id(),
            'keterangan'     => $keterangan,
            'user_id'        => Auth::id(),
            'created_at'     => now(),
        ]);
    }
}