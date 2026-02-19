<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockMutation;
use App\Models\InventoryBatch;
use App\Models\Barang;
use App\Models\Lokasi;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StockMutationController extends Controller
{
    public function index()
    {
        $this->authorize('view-stock-transaction');
        
        $user = Auth::user();
        $query = StockMutation::with(['barang', 'lokasiAsal', 'lokasiTujuan', 'createdBy']);

        if (!$user->hasRole(['SA', 'PIC', 'ASD', 'ACC', 'IMS'])) {
            $query->where(function($q) use ($user) {
                $q->where('lokasi_asal_id', $user->lokasi_id)
                  ->orWhere('lokasi_tujuan_id', $user->lokasi_id);
            });
        }

        $mutations = $query->latest()->paginate(15);
        return view('admin.stock_mutations.index', compact('mutations'));
    }

    public function create()
    {
        $this->authorize('create-stock-transaction');
        $user = Auth::user();

        if ($user->hasRole(['SA', 'PIC', 'ACC', 'IMS', 'ASD'])) {
            $lokasiAsal = Lokasi::where('is_active', true)->orderBy('nama_lokasi')->get();
        } else {
            if (!$user->lokasi_id) {
                return redirect()->route('admin.home')->with('error', 'Akun Anda tidak terasosiasi dengan lokasi manapun.');
            }
            $lokasiAsal = Lokasi::where('id', $user->lokasi_id)->get();
        }

        $lokasiTujuan = Lokasi::where('is_active', true)
                              ->orderBy('nama_lokasi')
                              ->get();

        return view('admin.stock_mutations.create', compact('lokasiAsal', 'lokasiTujuan'));
    }

    public function store(Request $request)
    {
        $this->authorize('create-stock-transaction');
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'barang_id'         => 'required|exists:barangs,id',
            'lokasi_asal_id'    => 'required|exists:lokasi,id',
            'lokasi_tujuan_id'  => 'required|exists:lokasi,id|different:lokasi_asal_id',
            'jumlah'            => 'required|integer|min:1',
            'keterangan'        => 'nullable|string|max:255',
        ], [
            'lokasi_tujuan_id.different' => 'Lokasi tujuan tidak boleh sama dengan lokasi asal.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        if (!$user->hasRole(['SA', 'PIC', 'ASD']) && $request->lokasi_asal_id != $user->lokasi_id) {
             return back()->with('error', 'Anda tidak memiliki akses untuk memindahkan barang dari lokasi ini.')->withInput();
        }

        $barang = Barang::findOrFail($request->barang_id);
        
        $totalStock = InventoryBatch::where('lokasi_id', $request->lokasi_asal_id)
            ->where('barang_id', $request->barang_id)
            ->sum('quantity');

        if ($totalStock < $request->jumlah) {
            return back()->with('error', "Gagal! Stok di lokasi asal tidak mencukupi. (Tersedia: {$totalStock})")->withInput();
        }

        $sisaPrediksi = $totalStock - $request->jumlah;
        if ($sisaPrediksi < $barang->stok_minimum) {
             return back()->with('error', "Gagal! Mutasi ini melanggar batas stok minimum ({$barang->stok_minimum}).")->withInput();
        }

        DB::beginTransaction();
        try {
            StockMutation::create([
                'nomor_mutasi'     => StockMutation::generateNomorMutasi(),
                'barang_id'        => $request->barang_id,
                'lokasi_asal_id'   => $request->lokasi_asal_id,
                'lokasi_tujuan_id' => $request->lokasi_tujuan_id,
                'jumlah'           => $request->jumlah,
                'keterangan'       => $request->keterangan,
                'status'           => 'PENDING_APPROVAL',
                'created_by'       => $user->id,
            ]);

            DB::commit();
            return redirect()->route('admin.stock-mutations.index')
                ->with('success', 'Permintaan mutasi berhasil dibuat dan menunggu persetujuan.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage())->withInput();
        }
    }

    public function approve(StockMutation $stockMutation)
    {
        $this->authorize('approve-stock-transaction', $stockMutation);

        if ($stockMutation->status !== 'PENDING_APPROVAL') {
            return back()->with('error', 'Hanya mutasi dengan status PENDING yang dapat diproses.');
        }

        DB::beginTransaction();
        try {
            $jumlahToMutate = $stockMutation->jumlah;
            $barang = $stockMutation->barang;
            $batches = InventoryBatch::where('lokasi_id', $stockMutation->lokasi_asal_id)
                   ->where('barang_id', $stockMutation->barang_id)
                   ->where('quantity', '>', 0)
                   ->orderBy('created_at', 'asc')
                   ->lockForUpdate()
                   ->get();

            $totalStockRealtime = $batches->sum('quantity');

            if ($totalStockRealtime < $jumlahToMutate) {
                throw new \Exception("Stok fisik tidak mencukupi saat proses approval. (Tersedia: {$totalStockRealtime})");
            }

            if (($totalStockRealtime - $jumlahToMutate) < $barang->stok_minimum) {
                throw new \Exception("Approval dibatalkan! Sisa stok akan menembus batas minimum ({$barang->stok_minimum}).");
            }

            $sisaPermintaan = $jumlahToMutate;

            foreach ($batches as $batch) {
                if ($sisaPermintaan <= 0) break;

                $ambil = min($batch->quantity, $sisaPermintaan);
                
                $stokAwalBatch = $batch->quantity;
                $batch->decrement('quantity', $ambil);

                StockMovement::create([
                    'barang_id'      => $stockMutation->barang_id,
                    'lokasi_id'      => $stockMutation->lokasi_asal_id,
                    'rak_id'         => $batch->rak_id,
                    'jumlah'         => -$ambil,
                    'stok_sebelum'   => $stokAwalBatch,
                    'stok_sesudah'   => $stokAwalBatch - $ambil,
                    'referensi_type' => get_class($stockMutation),
                    'referensi_id'   => $stockMutation->id,
                    'keterangan'     => 'Mutasi Keluar ke: ' . $stockMutation->lokasiTujuan->nama_lokasi,
                    'user_id'        => Auth::id(),
                ]);

                $sisaPermintaan -= $ambil;
            }

            if ($sisaPermintaan > 0) {
                throw new \Exception("Terjadi anomali saat alokasi batch. Silakan coba lagi.");
            }

            $stockMutation->update([
                'status'      => 'IN_TRANSIT',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            DB::commit();
            return redirect()->route('admin.stock-mutations.index')
                ->with('success', 'Mutasi disetujui. Stok asal telah dipotong dan status berubah menjadi IN TRANSIT.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal memproses: ' . $e->getMessage());
        }
    }

    public function reject(Request $request, StockMutation $stockMutation)
    {
        $this->authorize('approve-stock-transaction', $stockMutation);
        
        $request->validate([
            'rejection_reason' => 'required|string|min:5|max:255'
        ], [
            'rejection_reason.required' => 'Alasan penolakan wajib diisi.',
            'rejection_reason.min' => 'Alasan penolakan terlalu singkat.',
        ]);

        if ($stockMutation->status !== 'PENDING_APPROVAL') {
             return back()->with('error', 'Permintaan sudah diproses sebelumnya.');
        }

        $stockMutation->update([
            'status'           => 'REJECTED',
            'rejection_reason' => $request->rejection_reason,
            'approved_by'      => Auth::id(),
            'approved_at'      => now(),
        ]);

        return redirect()->route('admin.stock-mutations.index')->with('success', 'Permintaan mutasi ditolak.');
    }

    public function show(StockMutation $stockMutation)
    {
        $stockMutation->load(['barang', 'lokasiAsal', 'lokasiTujuan', 'createdBy', 'approvedBy', 'receivedBy']);
        return view('admin.stock_mutations.show', compact('stockMutation'));
    }

    
    public function getPartsWithStock(Lokasi $lokasi)
    {
        $barangs = Barang::where('is_active', true)->whereHas('inventoryBatches', function($q) use ($lokasi) {
                $q->where('lokasi_id', $lokasi->id)->where('quantity', '>', 0);
            })
            ->select('id', 'part_name', 'part_code')
            ->orderBy('part_name')
            ->get();

        return response()->json($barangs);
    }

    public function getPartStockDetails(Request $request)
    {
        $request->validate([
            'lokasi_id' => 'required|exists:lokasi,id',
            'barang_id' => 'required|exists:barangs,id',
        ]);

        $totalStock = InventoryBatch::where('lokasi_id', $request->lokasi_id)
            ->where('barang_id', $request->barang_id)
            ->sum('quantity');

        $barang = Barang::find($request->barang_id);

        return response()->json([
            'total_stock'  => $totalStock,
            'stok_minimum' => $barang->stok_minimum ?? 0
        ]);
    }
}