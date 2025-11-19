<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockAdjustment;
use App\Models\InventoryBatch;
use App\Models\Barang; // GANTI PART JADI BARANG
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
        // Ubah with('part') jadi with('barang')
        $query = StockAdjustment::with(['barang', 'lokasi', 'rak', 'createdBy', 'approvedBy']);

        if (!$user->hasRole(['SA', 'PIC', 'MA'])) {
            if ($user->lokasi_id) {
                $query->where('lokasi_id', $user->lokasi_id);
            } else {
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

        // PERBAIKAN DISINI: Hapus where('is_active', true) dan gunakan 'part_name'
        $barangs = Barang::orderBy('part_name')->get();

        $userLokasi = null;
        $allLokasi = collect();

        if ($user->lokasi_id) {
            $userLokasi = Lokasi::where('id', $user->lokasi_id)->where('is_active', true)->first();
        }

        if ($user->hasRole(['SA', 'PIC'])) {
            $allLokasi = Lokasi::where('is_active', true)->orderBy('nama_lokasi')->get();
        } elseif (!$userLokasi) {
             return redirect()->route('admin.stock-adjustments.index')->with('error', 'Akun Anda tidak terhubung ke lokasi aktif manapun.');
        }

        // Kirim variabel $barangs ke view
        return view('admin.stock_adjustments.create', compact('userLokasi', 'allLokasi', 'barangs'));
    }

    public function getRaksByLokasi(Lokasi $lokasi)
    {
        $raks = Rak::where('lokasi_id', $lokasi->id)
                   ->whereIn('tipe_rak', ['PENYIMPANAN', 'KARANTINA'])
                   ->where('is_active', true)
                   ->get();
        return response()->json($raks);
    }

    public function store(Request $request)
     {
         $this->authorize('create-stock-adjustment');
         $validated = $request->validate([
             'barang_id' => 'required|exists:barangs,id', // GANTI part_id
             'lokasi_id' => 'required|exists:lokasi,id',
             'rak_id'    => 'required|exists:raks,id',
             'tipe'      => 'required|in:TAMBAH,KURANG',
             'jumlah'    => 'required|integer|min:1',
             'alasan'    => 'required|string',
         ]);

          // Cek validitas rak
          $rakIsValid = Rak::where('id', $validated['rak_id'])
                           ->where('lokasi_id', $validated['lokasi_id'])
                           ->exists();

          if (!$rakIsValid) {
              return back()->withInput()->withErrors(['rak_id' => 'Rak yang dipilih tidak valid untuk lokasi yang dipilih.']);
          }

         StockAdjustment::create([
             'barang_id' => $validated['barang_id'], // GANTI part_id
             'lokasi_id' => $validated['lokasi_id'],
             'rak_id'    => $validated['rak_id'],
             'tipe'      => $validated['tipe'],
             'jumlah'    => $validated['jumlah'],
             'alasan'    => $validated['alasan'],
             'status'    => 'PENDING_APPROVAL',
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
                $barang_id = $stockAdjustment->barang_id; // GANTI part_id
                $rak_id = $stockAdjustment->rak_id;
                $lokasi_id = $stockAdjustment->lokasi_id;
                $jumlahToAdjust = $stockAdjustment->jumlah;
                $tipe = $stockAdjustment->tipe;

                $stokSebelum = InventoryBatch::where('barang_id', $barang_id) // GANTI part_id
                    ->where('rak_id', $rak_id)
                    ->sum('quantity');

                $stokSesudah = 0;

                if ($tipe === 'KURANG') {
                    if ($stokSebelum < $jumlahToAdjust) {
                        throw new \Exception('Stok tidak mencukupi. Stok tersedia: ' . $stokSebelum . ', dibutuhkan: ' . $jumlahToAdjust);
                    }

                    $batches = InventoryBatch::where('barang_id', $barang_id) // GANTI part_id
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
                        'barang_id' => $barang_id, // GANTI part_id
                        'rak_id' => $rak_id,
                        'lokasi_id' => $lokasi_id,
                        'quantity' => $jumlahToAdjust,
                        'receiving_detail_id' => null,
                    ]);
                    $stokSesudah = $stokSebelum + $jumlahToAdjust;
                }

                StockMovement::create([
                    'barang_id' => $barang_id, // GANTI part_id
                    'lokasi_id' => $lokasi_id,
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

    // ... method reject() sama logicnya, tidak ada perubahan signifikan ...
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
