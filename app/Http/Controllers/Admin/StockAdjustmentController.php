<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockAdjustment;
use App\Models\InventoryBatch;
use App\Models\Barang;
use App\Models\StockMovement;
use App\Models\Lokasi;
use App\Models\Rak;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StockAdjustmentController extends Controller
{
    public function index()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        
        $query = StockAdjustment::with(['barang', 'lokasi', 'rak', 'createdBy', 'approvedBy'])
            ->latest();

        // Filter Role
        if (!$user->hasRole(['SA', 'PIC', 'MA', 'ACC', 'SMD'])) {
            if ($user->lokasi_id) {
                $query->where('lokasi_id', $user->lokasi_id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $adjustments = $query->get();
        return view('admin.stock_adjustments.index', compact('adjustments'));
    }

    public function create()
    {
        $this->authorize('create-stock-adjustment');
        $user = Auth::user();

        // Load lokasi berdasarkan role
        if ($user->hasRole(['SA', 'PIC', 'ACC', 'SMD', 'MA'])) {
            $lokasis = Lokasi::where('is_active', true)->orderBy('nama_lokasi')->get();
        } else {
            if(!$user->lokasi_id) {
                return redirect()->route('admin.stock-adjustments.index')->with('error', 'Akun tidak terhubung lokasi.');
            }
            $lokasis = Lokasi::where('id', $user->lokasi_id)->get();
        }

        return view('admin.stock_adjustments.create', compact('lokasis'));
    }

    public function store(Request $request)
    {
        $this->authorize('create-stock-adjustment');

        $validator = Validator::make($request->all(), [
            'lokasi_id' => 'required|exists:lokasi,id',
            'rak_id'    => 'required|exists:raks,id',
            'barang_id' => 'required|exists:barangs,id',
            'tipe'      => 'required|in:TAMBAH,KURANG',
            'jumlah'    => 'required|integer|min:1',
            'alasan'    => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Validasi Rak milik Lokasi
        $rakExists = Rak::where('id', $request->rak_id)
            ->where('lokasi_id', $request->lokasi_id)
            ->exists();
            
        if (!$rakExists) {
            return back()->with('error', 'Rak tidak valid untuk lokasi yang dipilih.')->withInput();
        }

        // Validasi Stok Awal (Soft Check)
        if ($request->tipe === 'KURANG') {
            $currentStock = InventoryBatch::where('lokasi_id', $request->lokasi_id)
                ->where('rak_id', $request->rak_id)
                ->where('barang_id', $request->barang_id)
                ->sum('quantity');

            if ($request->jumlah > $currentStock) {
                return back()->with('error', "Gagal! Anda ingin mengurangi {$request->jumlah}, tapi stok di Rak tersebut hanya {$currentStock}.")->withInput();
            }
        }

        StockAdjustment::create([
            'barang_id'  => $request->barang_id,
            'lokasi_id'  => $request->lokasi_id,
            'rak_id'     => $request->rak_id,
            'tipe'       => $request->tipe,
            'jumlah'     => $request->jumlah,
            'alasan'     => $request->alasan,
            'status'     => 'PENDING_APPROVAL',
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('admin.stock-adjustments.index')
            ->with('success', 'Permintaan adjustment berhasil dibuat. Menunggu persetujuan.');
    }

    public function approve(StockAdjustment $stockAdjustment)
    {
        $this->authorize('approve-stock-adjustment', $stockAdjustment);

        if ($stockAdjustment->status !== 'PENDING_APPROVAL') {
            return back()->with('error', 'Status adjustment tidak valid untuk diproses.');
        }

        DB::beginTransaction();
        try {
            $qty = $stockAdjustment->jumlah;
            
            if ($stockAdjustment->tipe === 'KURANG') {
                // --- PESSIMISTIC LOCKING ---
                $batches = InventoryBatch::where('barang_id', $stockAdjustment->barang_id)
                    ->where('lokasi_id', $stockAdjustment->lokasi_id)
                    ->where('rak_id', $stockAdjustment->rak_id) // Spesifik Rak
                    ->where('quantity', '>', 0)
                    ->orderBy('created_at', 'asc') // FIFO
                    ->lockForUpdate()
                    ->get();

                $totalAvailable = $batches->sum('quantity');

                if ($totalAvailable < $qty) {
                    throw new \Exception("Stok fisik berubah saat proses approval! Tersedia: {$totalAvailable}, Diminta Kurang: {$qty}");
                }

                $remaining = $qty;
                
                foreach ($batches as $batch) {
                    if ($remaining <= 0) break;
                    
                    $take = min($batch->quantity, $remaining);
                    
                    // Update Batch
                    $stokAwal = $batch->quantity;
                    $batch->decrement('quantity', $take);
                    
                    // Catat Movement
                    StockMovement::create([
                        'barang_id'    => $stockAdjustment->barang_id,
                        'lokasi_id'    => $stockAdjustment->lokasi_id,
                        'rak_id'       => $stockAdjustment->rak_id,
                        'jumlah'       => -$take,
                        'stok_sebelum' => $stokAwal,
                        'stok_sesudah' => $stokAwal - $take,
                        'referensi_type' => get_class($stockAdjustment),
                        'referensi_id'   => $stockAdjustment->id,
                        'keterangan'     => "Adjustment (KURANG): " . $stockAdjustment->alasan,
                        'user_id'        => Auth::id(),
                    ]);

                    $remaining -= $take;
                }

            } else {
                // TIPE: TAMBAH
                // Buat Batch Baru
                // Kita ambil stok terakhir untuk log history movement (opsional lock)
                $currentTotal = InventoryBatch::where('barang_id', $stockAdjustment->barang_id)
                    ->where('rak_id', $stockAdjustment->rak_id)
                    ->sum('quantity');

                InventoryBatch::create([
                    'barang_id' => $stockAdjustment->barang_id,
                    'lokasi_id' => $stockAdjustment->lokasi_id,
                    'rak_id'    => $stockAdjustment->rak_id,
                    'quantity'  => $qty,
                    'receiving_detail_id' => null // Adjustment manual
                ]);

                StockMovement::create([
                    'barang_id'    => $stockAdjustment->barang_id,
                    'lokasi_id'    => $stockAdjustment->lokasi_id,
                    'rak_id'       => $stockAdjustment->rak_id,
                    'jumlah'       => $qty,
                    'stok_sebelum' => $currentTotal,
                    'stok_sesudah' => $currentTotal + $qty,
                    'referensi_type' => get_class($stockAdjustment),
                    'referensi_id'   => $stockAdjustment->id,
                    'keterangan'     => "Adjustment (TAMBAH): " . $stockAdjustment->alasan,
                    'user_id'        => Auth::id(),
                ]);
            }

            $stockAdjustment->update([
                'status' => 'APPROVED',
                'approved_by' => Auth::id(),
                'approved_at' => now()
            ]);

            DB::commit();
            return back()->with('success', 'Adjustment berhasil disetujui dan stok diperbarui.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal memproses: ' . $e->getMessage());
        }
    }

    public function reject(Request $request, StockAdjustment $stockAdjustment)
    {
        $this->authorize('approve-stock-adjustment', $stockAdjustment);
        
        $request->validate([
            'rejection_reason' => 'required|string|max:255'
        ]);

        if ($stockAdjustment->status !== 'PENDING_APPROVAL') {
            return back()->with('error', 'Status adjustment tidak valid.');
        }

        $stockAdjustment->update([
            'status' => 'REJECTED',
            'rejection_reason' => $request->rejection_reason,
            'approved_by' => Auth::id(),
            'approved_at' => now()
        ]);

        return back()->with('success', 'Permintaan adjustment ditolak.');
    }

    // --- API for Ajax ---
    public function getRaksByLokasi(Lokasi $lokasi)
    {
        return response()->json($lokasi->raks()->orderBy('nama_rak')->get());
    }

    public function checkStock(Request $request)
    {
        // Validasi input API
        if(!$request->lokasi_id || !$request->rak_id || !$request->barang_id) {
             return response()->json(['stock' => 0]);
        }

        $stock = InventoryBatch::where('lokasi_id', $request->lokasi_id)
            ->where('rak_id', $request->rak_id)
            ->where('barang_id', $request->barang_id)
            ->sum('quantity');

        return response()->json(['stock' => (int)$stock]);
    }
}