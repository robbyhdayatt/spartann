<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockAdjustment;
use App\Models\Lokasi;
use App\Models\Rak;
use App\Models\Barang;
use App\Models\InventoryBatch;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockAdjustmentController extends Controller
{
    public function index()
    {
        $this->authorize('view-stock-adjustment');
        
        $user = Auth::user();
        $query = StockAdjustment::with(['barang', 'lokasi', 'rak', 'createdBy']);
        if ($user->hasRole(['SA', 'PIC'])) {
        }
        elseif ($user->lokasi) {
            if ($user->lokasi->tipe === 'PUSAT') {
                $query->whereHas('lokasi', function($q) use ($user) {
                    $q->where('id', $user->lokasi_id)
                      ->orWhere('tipe', 'DEALER');
                });
            }
            elseif ($user->lokasi->tipe === 'GUDANG') {
                $query->where('lokasi_id', $user->lokasi_id);
            }
            elseif ($user->lokasi->tipe === 'DEALER') {
                $query->where('lokasi_id', $user->lokasi_id);
            }
        }
        else {
            $query->whereRaw('1 = 0');
        }

        $adjustments = $query->latest()->get();
        return view('admin.stock_adjustments.index', compact('adjustments'));
    }

    public function create()
    {
        $this->authorize('create-stock-adjustment');
        
        $user = Auth::user();
        $query = Lokasi::query();
        if ($user->hasRole(['SA', 'PIC'])) {
        }
        elseif ($user->lokasi) {
            if ($user->lokasi->tipe === 'PUSAT') {
                $query->where(function($q) use ($user) {
                    $q->where('id', $user->lokasi_id)
                      ->orWhere('tipe', 'DEALER');
                });
            }
            else {
                $query->where('id', $user->lokasi_id);
            }
        } 
        else {
            $query->whereRaw('1 = 0');
        }

        $lokasis = $query->where('is_active', true)->orderBy('nama_lokasi')->get();

        return view('admin.stock_adjustments.create', compact('lokasis'));
    }

    public function store(Request $request)
    {
        $this->authorize('create-stock-adjustment');

        $request->validate([
            'lokasi_id' => 'required|exists:lokasi,id',
            'rak_id'    => 'required|exists:raks,id',
            'barang_id' => 'required|exists:barangs,id',
            'tipe'      => 'required|in:TAMBAH,KURANG',
            'jumlah'    => 'required|integer|min:1',
            'alasan'    => 'required|string',
            'inventory_batch_id' => 'nullable|exists:inventory_batches,id'
        ]);

        $user = Auth::user();
        
        if (!$user->isGlobal()) {
            $targetLokasi = Lokasi::find($request->lokasi_id);
            if ($user->isPusat() && $targetLokasi->tipe === 'GUDANG') {
                 return back()->with('error', 'Akses Ditolak: Pusat tidak boleh mengelola Gudang Part.');
            }
            if (($user->isGudang() || $user->isDealer()) && $request->lokasi_id != $user->lokasi_id) {
                return back()->with('error', 'Akses Ditolak: Anda hanya boleh mengelola lokasi Anda sendiri.');
            }
        }

        StockAdjustment::create([
            'lokasi_id' => $request->lokasi_id,
            'rak_id'    => $request->rak_id,
            'barang_id' => $request->barang_id,
            'inventory_batch_id' => $request->inventory_batch_id,
            'tipe'      => $request->tipe,
            'jumlah'    => $request->jumlah,
            'alasan'    => $request->alasan,
            'status'    => 'PENDING_APPROVAL',
            'created_by'=> Auth::id(),
        ]);

        return redirect()->route('admin.stock-adjustments.index')
            ->with('success', 'Pengajuan penyesuaian stok berhasil dibuat. Menunggu persetujuan.');
    }

    public function approve(StockAdjustment $stockAdjustment)
    {
        $this->authorize('approve-stock-adjustment');

        if ($stockAdjustment->status !== 'PENDING_APPROVAL') {
            return back()->with('error', 'Status adjustment tidak valid.');
        }

        $user = Auth::user();
        if (!$user->isGlobal()) {
             if ($user->hasRole('KG') && $stockAdjustment->lokasi_id != $user->lokasi_id) {
                 return back()->with('error', 'Anda hanya boleh menyetujui adjustment di gudang Anda.');
             }
             if ($user->hasRole('KC') && $stockAdjustment->lokasi_id != $user->lokasi_id) {
                 return back()->with('error', 'Anda hanya boleh menyetujui adjustment di dealer Anda.');
             }
        }

        DB::beginTransaction();
        try {
            $qty = $stockAdjustment->jumlah;
            $batch = null;
            if ($stockAdjustment->tipe === 'TAMBAH') {
                if ($stockAdjustment->inventory_batch_id) {
                    $batch = InventoryBatch::find($stockAdjustment->inventory_batch_id);
                    if ($batch) {
                        $stokAwal = $batch->quantity;
                        $batch->increment('quantity', $qty);
                        $this->logMovement($stockAdjustment, $batch, $qty, $stokAwal, $stokAwal + $qty);
                    }
                } 
                else {
                    $batch = InventoryBatch::create([
                        'barang_id' => $stockAdjustment->barang_id,
                        'lokasi_id' => $stockAdjustment->lokasi_id,
                        'rak_id'    => $stockAdjustment->rak_id,
                        'quantity'  => $qty,
                        'created_at' => now()
                    ]);
                    $this->logMovement($stockAdjustment, $batch, $qty, 0, $qty);
                }
            }
            elseif ($stockAdjustment->tipe === 'KURANG') {
                $sisaPotong = $qty;
                if ($stockAdjustment->inventory_batch_id) {
                    $batch = InventoryBatch::find($stockAdjustment->inventory_batch_id);
                    
                    if($batch && $batch->quantity >= $qty) {
                         $stokAwal = $batch->quantity;
                         $batch->decrement('quantity', $qty);
                         $this->logMovement($stockAdjustment, $batch, -$qty, $stokAwal, $stokAwal - $qty);
                         $sisaPotong = 0;
                    } else {
                         throw new \Exception("Stok pada batch yang dipilih tidak mencukupi/sudah berubah.");
                    }
                }
                else {
                    $batches = InventoryBatch::where('barang_id', $stockAdjustment->barang_id)
                        ->where('rak_id', $stockAdjustment->rak_id)
                        ->where('quantity', '>', 0)
                        ->orderBy('created_at', 'asc')
                        ->get();

                    foreach ($batches as $b) {
                        if ($sisaPotong <= 0) break;
                        $ambil = min($b->quantity, $sisaPotong);
                        
                        $stokAwal = $b->quantity;
                        $b->decrement('quantity', $ambil);
                        
                        $this->logMovement($stockAdjustment, $b, -$ambil, $stokAwal, $stokAwal - $ambil);
                        $sisaPotong -= $ambil;
                    }
                    
                    if ($sisaPotong > 0) {
                        throw new \Exception("Stok fisik di Rak ini tidak mencukupi untuk pengurangan.");
                    }
                }
            }

            $stockAdjustment->update([
                'status' => 'APPROVED',
                'approved_by' => Auth::id(),
                'approved_at' => now()
            ]);

            DB::commit();
            return back()->with('success', 'Adjustment disetujui dan stok telah diperbarui.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function reject(Request $request, StockAdjustment $stockAdjustment)
    {
        $this->authorize('approve-stock-adjustment');
        $request->validate(['rejection_reason' => 'required']);

        $stockAdjustment->update([
            'status' => 'REJECTED',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'rejection_reason' => $request->rejection_reason
        ]);

        return back()->with('success', 'Adjustment ditolak.');
    }

    public function getBarangs(Request $request)
    {
        $search = $request->q;
        $barangs = Barang::where('is_active', true)
            ->where(function($q) use ($search) {
                $q->where('part_name', 'LIKE', "%$search%")
                  ->orWhere('part_code', 'LIKE', "%$search%");
            })
            ->limit(20)
            ->get()
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'text' => $item->part_name . ' (' . $item->part_code . ')'
                ];
            });
        return response()->json($barangs);
    }

    public function getBatches(Request $request)
    {
        $request->validate([
            'barang_id' => 'required',
            'rak_id' => 'required'
        ]);

        $batches = InventoryBatch::where('barang_id', $request->barang_id)
            ->where('rak_id', $request->rak_id)
            ->where('quantity', '>', 0)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function($b) {
                return [
                    'id' => $b->id,
                    'text' => "Qty: {$b->quantity} | Tgl Masuk: " . $b->created_at->format('d/m/Y') . " (ID: {$b->id})"
                ];
            });

        return response()->json($batches);
    }

    private function logMovement($adj, $batch, $qty, $awal, $akhir)
    {
        StockMovement::create([
            'barang_id' => $adj->barang_id,
            'lokasi_id' => $adj->lokasi_id,
            'rak_id'    => $adj->rak_id,
            'jumlah'    => $qty,
            'stok_sebelum' => $awal,
            'stok_sesudah' => $akhir,
            'referensi_type' => StockAdjustment::class,
            'referensi_id'   => $adj->id,
            'keterangan'     => "Adjustment: " . $adj->tipe . " (" . ($adj->alasan ?? '-') . ")",
            'user_id'        => Auth::id(),
            'created_at'     => now()
        ]);
    }
}