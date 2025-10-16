<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryBatch;
use App\Models\Rak;
use App\Models\Part;
use App\Models\Lokasi; // DIUBAH DARI GUDANG
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class QuarantineStockController extends Controller
{
    public function __construct()
    {

    }

    public function index()
    {
        $this->authorize('view-quarantine-stock');
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $lokasiFilterId = null;

        // User SA dan PIC bisa melihat semua, selain itu hanya bisa melihat lokasi sendiri
        if (!$user->hasRole(['SA', 'PIC'])) {
            $lokasiFilterId = $user->gudang_id;
        }

        $quarantineQuery = InventoryBatch::whereHas('rak', function ($query) {
            $query->where('tipe_rak', 'KARANTINA');
        })->where('quantity', '>', 0);

        if ($lokasiFilterId) {
            $quarantineQuery->where('gudang_id', $lokasiFilterId);
        }

        // Logika query disederhanakan dengan eager loading
        $quarantineItems = $quarantineQuery
            ->select('part_id', 'rak_id', 'gudang_id', DB::raw('SUM(quantity) as total_quantity'))
            ->groupBy('part_id', 'rak_id', 'gudang_id')
            ->with(['part', 'rak', 'lokasi']) // Eager load relasi
            ->get();

        // Ambil rak penyimpanan berdasarkan filter lokasi user
        $storageRaksQuery = Rak::where('tipe_rak', 'PENYIMPANAN')->where('is_active', true);
        if ($lokasiFilterId) {
            $storageRaksQuery->where('gudang_id', $lokasiFilterId);
        }
        $storageRaks = $storageRaksQuery->get()->groupBy('gudang_id');

        return view('admin.quarantine_stock.index', compact('quarantineItems', 'storageRaks'));
    }

    public function process(Request $request)
    {
        $this->authorize('manage-quarantine-stock');
        $validated = $request->validate([
            'part_id' => 'required|exists:parts,id',
            'rak_id' => 'required|exists:raks,id',
            'gudang_id' => 'required|exists:lokasi,id', // DIUBAH ke tabel lokasi
            'action' => 'required|in:return_to_stock,write_off',
            'quantity' => 'required|integer|min:1',
            'destination_rak_id' => 'nullable|required_if:action,return_to_stock|exists:raks,id',
            'reason' => 'nullable|required_if:action,write_off|string|max:255',
        ]);

        $partId = $validated['part_id'];
        $rakId = $validated['rak_id'];
        $gudangId = $validated['gudang_id'];
        $quantityToProcess = $validated['quantity'];
        $message = '';

        try {
            DB::transaction(function () use ($validated, $partId, $rakId, $gudangId, $quantityToProcess, &$message) {

                $currentStock = InventoryBatch::where('part_id', $partId)->where('rak_id', $rakId)->sum('quantity');
                if ($quantityToProcess > $currentStock) {
                    throw new \Exception('Jumlah melebihi stok karantina. Stok tersedia: ' . $currentStock);
                }

                if ($validated['action'] === 'return_to_stock') {
                    $destinationRak = Rak::findOrFail($validated['destination_rak_id']);
                    if ($gudangId != $destinationRak->gudang_id) {
                        throw new \Exception('Rak tujuan harus berada di lokasi yang sama.');
                    }

                    $this->reduceStockFromBatches($partId, $rakId, $quantityToProcess, 'Keluar dari karantina ke rak ' . $destinationRak->kode_rak);

                    // Logika penambahan stok diperbaiki
                    $this->addStockToBatch($partId, $destinationRak->id, $gudangId, $quantityToProcess, 'Masuk ke stok dari karantina');

                    $message = 'Barang berhasil dikembalikan ke stok penjualan.';

                } elseif ($validated['action'] === 'write_off') {
                    // Proses ini sudah benar, membuat pengajuan adjustment
                    StockAdjustment::create([
                        'part_id' => $partId,
                        'gudang_id' => $gudangId,
                        'rak_id' => $rakId,
                        'tipe' => 'KURANG',
                        'jumlah' => $quantityToProcess,
                        'alasan' => $validated['reason'],
                        'status' => 'PENDING_APPROVAL',
                        'created_by' => auth()->id(),
                    ]);
                    $message = 'Permintaan write-off berhasil diajukan dan menunggu persetujuan.';
                }
            });

            return redirect()->route('admin.quarantine-stock.index')->with('success', $message);

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage())->withInput();
        }
    }

    private function reduceStockFromBatches($partId, $rakId, $quantityToReduce, $keterangan)
    {
        $batches = InventoryBatch::where('part_id', $partId)->where('rak_id', $rakId)
            ->where('quantity', '>', 0)->orderBy('created_at', 'asc')->get();

        $remainingQtyToReduce = $quantityToReduce;
        foreach ($batches as $batch) {
            if ($remainingQtyToReduce <= 0) break;
            
            $stokTotalSebelum = InventoryBatch::where('part_id', $partId)->where('gudang_id', $batch->gudang_id)->sum('quantity');

            $qtyToTake = min($batch->quantity, $remainingQtyToReduce);
            $batch->decrement('quantity', $qtyToTake);
            
            StockMovement::create([
                'part_id' => $partId, 'gudang_id' => $batch->gudang_id, 'rak_id' => $rakId,
                'jumlah' => -$qtyToTake, 'stok_sebelum' => $stokTotalSebelum, 'stok_sesudah' => $stokTotalSebelum - $qtyToTake,
                'referensi_type' => 'App\Models\User', 'referensi_id' => auth()->id(), 'user_id' => auth()->id(),
                'keterangan' => $keterangan,
            ]);

            if ($batch->quantity <= 0) {
                $batch->delete();
            }
        }
    }
    
    // Helper function baru untuk menambah stok ke batch
    private function addStockToBatch($partId, $rakId, $gudangId, $quantityToAdd, $keterangan)
    {
        $stokTotalSebelum = InventoryBatch::where('part_id', $partId)->where('gudang_id', $gudangId)->sum('quantity');
        
        $batch = InventoryBatch::firstOrCreate(
            ['part_id' => $partId, 'rak_id' => $rakId, 'gudang_id' => $gudangId],
            ['quantity' => 0, 'receiving_detail_id' => null]
        );
        $batch->increment('quantity', $quantityToAdd);

        StockMovement::create([
            'part_id' => $partId, 'gudang_id' => $gudangId, 'rak_id' => $rakId,
            'jumlah' => $quantityToAdd, 'stok_sebelum' => $stokTotalSebelum, 'stok_sesudah' => $stokTotalSebelum + $quantityToAdd,
            'referensi_type' => 'App\Models\User', 'referensi_id' => auth()->id(), 'user_id' => auth()->id(),
            'keterangan' => $keterangan,
        ]);
    }
}