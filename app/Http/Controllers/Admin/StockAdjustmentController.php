<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockAdjustment;
use App\Models\InventoryBatch;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\Lokasi;
use App\Models\Rak;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockAdjustmentController extends Controller
{
    public function index()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $query = StockAdjustment::with(['part', 'lokasi', 'rak', 'createdBy', 'approvedBy']);

        // ++ PERBAIKAN: Batasi data berdasarkan lokasi user ++
        // Asumsi: Peran 'SA', 'PIC', 'MA' dapat melihat semua data. Sesuaikan jika perlu.
        if (!$user->hasRole(['SA', 'PIC', 'MA'])) {
            // Pastikan user memiliki gudang_id
            if ($user->gudang_id) {
                $query->where('gudang_id', $user->gudang_id);
            } else {
                // Jika user tidak punya lokasi, jangan tampilkan data apa pun untuk keamanan.
                $query->whereRaw('1 = 0');
            }
        }

        $adjustments = $query->latest()->get();
        return view('admin.stock_adjustments.index', compact('adjustments'));
    }

    public function create()
    {
        $this->authorize('create-stock-adjustment');
        
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $parts = Part::where('is_active', true)->orderBy('nama_part')->get();
        $lokasi = null;

        if ($user->gudang_id) {
            $lokasi = Lokasi::where('id', $user->gudang_id)->first();
        }

        if (!$lokasi) {
             return redirect()->route('admin.stock-adjustments.index')->with('error', 'Akun Anda tidak terhubung ke lokasi manapun.');
        }

        return view('admin.stock_adjustments.create', compact('lokasi', 'parts'));
    }

    // Fungsi API untuk mengambil data rak
    public function getRaksByLokasi(Lokasi $lokasi)
    {
        $raks = Rak::where('gudang_id', $lokasi->id)
                   ->whereIn('tipe_rak', ['PENYIMPANAN', 'KARANTINA'])
                   ->where('is_active', true)
                   ->get();
        return response()->json($raks);
    }

    public function store(Request $request)
    {
        $this->authorize('create-stock-adjustment');
        $validated = $request->validate([
            'part_id' => 'required|exists:parts,id',
            'gudang_id' => 'required|exists:lokasi,id',
            'rak_id' => 'required|exists:raks,id',
            'tipe' => 'required|in:TAMBAH,KURANG',
            'jumlah' => 'required|integer|min:1',
            'alasan' => 'required|string',
        ]);

        StockAdjustment::create([
            'part_id' => $validated['part_id'],
            'gudang_id' => $validated['gudang_id'],
            'rak_id' => $validated['rak_id'],
            'tipe' => $validated['tipe'],
            'jumlah' => $validated['jumlah'],
            'alasan' => $validated['alasan'],
            'status' => 'PENDING_APPROVAL',
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('admin.stock-adjustments.index')->with('success', 'Permintaan adjusment stok berhasil dibuat dan menunggu persetujuan.');
    }

    public function approve(StockAdjustment $stockAdjustment)
    {
        $this->authorize('approve-stock-adjustment', $stockAdjustment);

        if ($stockAdjustment->status !== 'PENDING_APPROVAL') {
            return back()->with('error', 'Permintaan ini sudah diproses.');
        }

        try {
            DB::transaction(function () use ($stockAdjustment) {
                $part_id = $stockAdjustment->part_id;
                $rak_id = $stockAdjustment->rak_id;
                $gudang_id = $stockAdjustment->gudang_id;
                $jumlahToAdjust = $stockAdjustment->jumlah;
                $tipe = $stockAdjustment->tipe;

                $stokSebelum = InventoryBatch::where('part_id', $part_id)
                    ->where('rak_id', $rak_id)
                    ->sum('quantity');

                $stokSesudah = 0;

                if ($tipe === 'KURANG') {
                    if ($stokSebelum < $jumlahToAdjust) {
                        throw new \Exception('Stok tidak mencukupi. Stok tersedia: ' . $stokSebelum . ', dibutuhkan: ' . $jumlahToAdjust);
                    }

                    $batches = InventoryBatch::where('part_id', $part_id)
                        ->where('rak_id', $rak_id)
                        ->where('quantity', '>', 0)
                        ->orderBy('created_at', 'asc')
                        ->get();

                    $remainingQtyToReduce = $jumlahToAdjust;

                    foreach ($batches as $batch) {
                        if ($remainingQtyToReduce <= 0) break;
                        $qtyInBatch = $batch->quantity;

                        if ($qtyInBatch >= $remainingQtyToReduce) {
                            $batch->quantity -= $remainingQtyToReduce;
                            $remainingQtyToReduce = 0;
                        } else {
                            $remainingQtyToReduce -= $qtyInBatch;
                            $batch->quantity = 0;
                        }

                        if ($batch->quantity == 0) {
                            $batch->delete();
                        } else {
                            $batch->save();
                        }
                    }
                    $stokSesudah = $stokSebelum - $jumlahToAdjust;

                } else { // Tipe 'TAMBAH'
                    InventoryBatch::create([
                        'part_id' => $part_id,
                        'rak_id' => $rak_id,
                        'gudang_id' => $gudang_id,
                        'quantity' => $jumlahToAdjust,
                        'receiving_detail_id' => null,
                    ]);
                    $stokSesudah = $stokSebelum + $jumlahToAdjust;
                }

                StockMovement::create([
                    'part_id' => $part_id,
                    'gudang_id' => $gudang_id,
                    'rak_id' => $rak_id,
                    'jumlah' => ($tipe === 'TAMBAH' ? $jumlahToAdjust : -$jumlahToAdjust),
                    'stok_sebelum' => $stokSebelum,
                    'stok_sesudah' => $stokSesudah,
                    'referensi_type' => get_class($stockAdjustment),
                    'referensi_id' => $stockAdjustment->id,
                    'keterangan' => "Adjustment: " . $stockAdjustment->alasan,
                    'user_id' => $stockAdjustment->created_by,
                ]);

                $stockAdjustment->status = 'APPROVED';
                $stockAdjustment->approved_by = Auth::id();
                $stockAdjustment->approved_at = now();
                $stockAdjustment->save();
            });

            return redirect()->route('admin.stock-adjustments.index')->with('success', 'Adjusment stok disetujui dan stok telah diperbarui.');

        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memproses persetujuan: ' . $e->getMessage());
        }
    }

    public function reject(Request $request, StockAdjustment $stockAdjustment)
    {
        $this->authorize('approve-stock-adjustment', $stockAdjustment);
        $request->validate(['rejection_reason' => 'required|string|min:10']);

        if ($stockAdjustment->status !== 'PENDING_APPROVAL') {
            return back()->with('error', 'Permintaan ini sudah diproses.');
        }

        $stockAdjustment->status = 'REJECTED';
        $stockAdjustment->rejection_reason = $request->rejection_reason;
        $stockAdjustment->approved_by = Auth::id();
        $stockAdjustment->approved_at = now();
        $stockAdjustment->save();

        return redirect()->route('admin.stock-adjustments.index')->with('success', 'Permintaan adjusment stok telah ditolak.');
    }
}