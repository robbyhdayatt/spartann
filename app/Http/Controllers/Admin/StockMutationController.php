<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockMutation;
use App\Models\InventoryBatch;
use App\Models\Barang;
use App\Models\Lokasi;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StockMutationController extends Controller
{
    // ... method index dan create TETAP SAMA ...
    public function index()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $query = StockMutation::with(['barang', 'lokasiAsal', 'lokasiTujuan', 'createdBy']);

        if (!$user->hasRole(['SA', 'PIC', 'MA', 'ACC', 'ASD'])) {
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
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // 1. Lokasi Asal (Sumber Barang)
        if ($user->hasRole(['SA', 'PIC', 'MA', 'ACC'])) {
            // Pusat/Admin boleh pilih lokasi asal mana saja KECUALI PUSAT
            $lokasiAsal = Lokasi::where('is_active', true)
                                ->where('tipe', '!=', 'PUSAT')
                                ->orderBy('nama_lokasi')
                                ->get();
        } else {
            // PC (Dealer) HANYA BISA memutasi dari lokasinya sendiri
            $lokasiAsal = Lokasi::where('id', $user->lokasi_id)->get();
        }

        // 2. Lokasi Tujuan (Penerima Barang)
        $lokasiTujuan = Lokasi::where('is_active', true)
                              ->where('tipe', '!=', 'PUSAT')
                              ->orderBy('nama_lokasi')
                              ->get();

        return view('admin.stock_mutations.create', compact('lokasiAsal', 'lokasiTujuan'));
    }

    public function store(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $this->authorize('create-stock-transaction');

        $validated = $request->validate([
            'barang_id' => 'required|exists:barangs,id',
            'lokasi_asal_id' => 'required|exists:lokasi,id',
            'lokasi_tujuan_id' => 'required|exists:lokasi,id|different:lokasi_asal_id',
            'jumlah' => 'required|integer|min:1',
            'keterangan' => 'nullable|string',
        ]);

        if (!$user->hasRole(['SA', 'PIC']) && $validated['lokasi_asal_id'] != $user->lokasi_id) {
             return back()->with('error', 'Anda hanya boleh melakukan mutasi dari lokasi Anda sendiri.')->withInput();
        }

        $lokasiAsal = Lokasi::find($validated['lokasi_asal_id']);
        $lokasiTujuan = Lokasi::find($validated['lokasi_tujuan_id']);

        if ($lokasiAsal->tipe === 'PUSAT' || $lokasiTujuan->tipe === 'PUSAT') {
            return back()->with('error', 'Mutasi yang melibatkan Gudang Pusat tidak diizinkan di menu ini.')->withInput();
        }

        // --- VALIDASI STOK TOTAL & MINIMUM ---
        $barang = Barang::findOrFail($validated['barang_id']);
        
        $totalStock = InventoryBatch::where('lokasi_id', $validated['lokasi_asal_id'])
            ->where('barang_id', $validated['barang_id'])
            ->sum('quantity');

        // Cek 1: Stok Fisik
        if ($totalStock < $validated['jumlah']) {
            return back()->with('error', 'Stok total di lokasi asal tidak mencukupi. Stok tersedia: ' . $totalStock)->withInput();
        }

        // Cek 2: Stok Minimum
        $sisaStok = $totalStock - $validated['jumlah'];
        if ($sisaStok < $barang->stok_minimum) {
             return back()->with('error', "Gagal! Mutasi ini akan menyebabkan stok {$barang->part_name} menembus batas minimum ({$barang->stok_minimum}). Sisa prediksi: {$sisaStok}.")->withInput();
        }

        $mutationData = $validated;
        $mutationData['nomor_mutasi'] = StockMutation::generateNomorMutasi();
        $mutationData['status'] = 'PENDING_APPROVAL';
        $mutationData['created_by'] = $user->id;
        $mutationData['rak_asal_id'] = null;

        StockMutation::create($mutationData);

        return redirect()->route('admin.stock-mutations.index')->with('success', 'Permintaan mutasi berhasil dibuat. Menunggu persetujuan ASD/Manager.');
    }

    public function approve(StockMutation $stockMutation)
    {
        $this->authorize('approve-stock-transaction', $stockMutation);

        if ($stockMutation->status !== 'PENDING_APPROVAL') {
            return back()->with('error', 'Hanya permintaan PENDING yang bisa diproses.');
        }

         try {
               DB::transaction(function () use ($stockMutation) {
                   $jumlahToMutate = $stockMutation->jumlah;
                   $barang = $stockMutation->barang;

                   // === ANTI RACE CONDITION: LOCK FOR UPDATE ===
                   $batches = InventoryBatch::where('lokasi_id', $stockMutation->lokasi_asal_id)
                         ->where('barang_id', $stockMutation->barang_id)
                         ->where('quantity', '>', 0)
                         ->orderBy('created_at', 'asc') // FIFO
                         ->lockForUpdate() // <--- LOCKING PENTING
                         ->get();

                   $totalStock = $batches->sum('quantity');

                   if ($totalStock < $jumlahToMutate) {
                       throw new \Exception('Gagal Approve! Stok saat ini tidak mencukupi (Tersedia: ' . $totalStock . ').');
                   }

                   // Validasi Minimum saat Approve (Double Check)
                   $sisaStok = $totalStock - $jumlahToMutate;
                   if ($sisaStok < $barang->stok_minimum) {
                        throw new \Exception("Gagal Approve! Stok akan menembus batas minimum ({$barang->stok_minimum}).");
                   }

                   $remainingToMutate = $jumlahToMutate;

                   foreach ($batches as $batch) {
                       if ($remainingToMutate <= 0) break;

                       $stokSebelum = $batch->quantity;
                       $stokKeluar = min($stokSebelum, $remainingToMutate);

                       $batch->decrement('quantity', $stokKeluar);
                       $remainingToMutate -= $stokKeluar;

                       StockMovement::create([
                           'barang_id' => $stockMutation->barang_id,
                           'lokasi_id' => $stockMutation->lokasi_asal_id,
                           'rak_id' => $batch->rak_id,
                           'jumlah' => -$stokKeluar,
                           'stok_sebelum' => $stokSebelum,
                           'stok_sesudah' => $batch->quantity, // Batch quantity baru
                           'referensi_type' => get_class($stockMutation),
                           'referensi_id' => $stockMutation->id,
                           'keterangan' => 'Mutasi Keluar ke ' . $stockMutation->lokasiTujuan->kode_lokasi . ' (Appr: ' . Auth::user()->name . ')',
                           'user_id' => Auth::id(),
                       ]);
                   }

                   if ($remainingToMutate > 0) {
                        throw new \Exception('Sistem Error: Gagal mengalokasikan stok FIFO sepenuhnya.');
                   }

                   $stockMutation->status = 'IN_TRANSIT';
                   $stockMutation->approved_by = Auth::id();
                   $stockMutation->approved_at = now();
                   $stockMutation->save();
               });
         } catch (\Exception $e) {
               DB::rollBack();
               return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
         }

          return redirect()->route('admin.stock-mutations.index')->with('success', 'Permintaan mutasi disetujui. Stok telah dipotong (FIFO) dan status menjadi IN TRANSIT.');
    }

    // ... method show dan reject TETAP SAMA ...
    public function show(StockMutation $stockMutation)
    {
        $stockMutation->load(['barang', 'lokasiAsal', 'lokasiTujuan', 'rakAsal', 'createdBy', 'approvedBy']);
        return view('admin.stock_mutations.show', compact('stockMutation'));
    }

    public function reject(Request $request, StockMutation $stockMutation)
    {
        $this->authorize('approve-stock-transaction', $stockMutation);
        $request->validate(['rejection_reason' => 'required|string|min:5']);
        if ($stockMutation->status !== 'PENDING_APPROVAL') return back()->with('error', 'Sudah diproses.');
        $stockMutation->status = 'REJECTED';
        $stockMutation->rejection_reason = $request->rejection_reason;
        $stockMutation->approved_by = Auth::id();
        $stockMutation->approved_at = now();
        $stockMutation->save();
        return redirect()->route('admin.stock-mutations.index')->with('success', 'Ditolak.');
    }

    // --- API Helper ---
    public function getPartsWithStock(Lokasi $lokasi)
    {
        $barangIds = InventoryBatch::where('lokasi_id', $lokasi->id)
            ->where('quantity', '>', 0)
            ->pluck('barang_id')
            ->unique();

        $barangs = Barang::whereIn('id', $barangIds)->orderBy('part_name')->get();
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
            'total_stock' => $totalStock,
            'stok_minimum' => $barang->stok_minimum ?? 0
        ]);
    }
}