<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockMutation;
use App\Models\InventoryBatch;
use App\Models\Part;
use App\Models\Lokasi;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StockMutationController extends Controller
{
    public function index()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $query = StockMutation::with(['part', 'lokasiAsal', 'lokasiTujuan', 'createdBy']);

        if (!$user->hasRole(['SA', 'PIC', 'MA'])) {
            $query->where(function($q) use ($user) {
                $q->where('gudang_asal_id', $user->gudang_id)
                  ->orWhere('gudang_tujuan_id', $user->gudang_id);
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

        if ($user->hasRole(['SA', 'PIC', 'MA'])) {
            $lokasiAsal = Lokasi::where('is_active', true)->orderBy('nama_gudang')->get();
        } else {
            $lokasiAsal = Lokasi::where('id', $user->gudang_id)->get();
        }

        $lokasiTujuan = Lokasi::where('is_active', true)->orderBy('nama_gudang')->get();

        return view('admin.stock_mutations.create', compact('lokasiAsal', 'lokasiTujuan'));
    }

    public function store(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $this->authorize('create-stock-transaction');

        $validated = $request->validate([
            'part_id' => 'required|exists:parts,id',
            'gudang_asal_id' => 'required|exists:lokasi,id',
            'gudang_tujuan_id' => 'required|exists:lokasi,id|different:gudang_asal_id',
            'jumlah' => 'required|integer|min:1',
            'keterangan' => 'nullable|string',
        ]);

        $lokasiAsal = Lokasi::find($validated['gudang_asal_id']);
        $lokasiTujuan = Lokasi::find($validated['gudang_tujuan_id']);
        if ($lokasiAsal->tipe === 'DEALER' && $lokasiTujuan->tipe === 'PUSAT') {
            return back()->with('error', 'Mutasi dari Dealer ke Gudang Pusat tidak diizinkan.')->withInput();
        }

        $totalStock = InventoryBatch::where('gudang_id', $validated['gudang_asal_id'])
            ->where('part_id', $validated['part_id'])
            ->sum('quantity');

        if ($totalStock < $validated['jumlah']) {
            return back()->with('error', 'Stok total di lokasi asal tidak mencukupi. Stok tersedia: ' . $totalStock)->withInput();
        }

        $mutationData = $validated;
        $mutationData['nomor_mutasi'] = StockMutation::generateNomorMutasi();
        $mutationData['status'] = 'PENDING_APPROVAL';
        $mutationData['created_by'] = $user->id;
        $mutationData['rak_asal_id'] = null;

        StockMutation::create($mutationData);

        // PERBAIKAN: Menggunakan tanda hubung (-) sesuai definisi di web.php
        return redirect()->route('admin.stock-mutations.index')->with('success', 'Permintaan mutasi berhasil dibuat.');
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
                   $totalStock = InventoryBatch::where('gudang_id', $stockMutation->gudang_asal_id)
                         ->where('part_id', $stockMutation->part_id)
                         ->sum('quantity');

                   if ($totalStock < $jumlahToMutate) {
                       throw new \Exception('Stok tidak mencukupi. Stok tersedia: ' . $totalStock);
                   }

                   $batches = InventoryBatch::where('gudang_id', $stockMutation->gudang_asal_id)
                         ->where('part_id', $stockMutation->part_id)
                         ->where('quantity', '>', 0)
                         ->orderBy('created_at', 'asc')
                         ->get();

                   $remainingToMutate = $jumlahToMutate;

                   foreach ($batches as $batch) {
                       if ($remainingToMutate <= 0) break;

                       $stokSebelum = $batch->quantity;
                       $stokKeluar = min($stokSebelum, $remainingToMutate);

                       $batch->decrement('quantity', $stokKeluar);
                       $remainingToMutate -= $stokKeluar;

                       StockMovement::create([
                           'part_id' => $stockMutation->part_id,
                           'gudang_id' => $stockMutation->gudang_asal_id,
                           'rak_id' => $batch->rak_id,
                           'jumlah' => -$stokKeluar,
                           'stok_sebelum' => $stokSebelum,
                           'stok_sesudah' => $batch->quantity,
                           'referensi_type' => get_class($stockMutation),
                           'referensi_id' => $stockMutation->id,
                           'keterangan' => 'Mutasi Keluar ke ' . $stockMutation->lokasiTujuan->kode_gudang,
                           'user_id' => Auth::id(),
                       ]);

                       if ($batch->quantity <= 0) {
                           $batch->delete();
                       }
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

          // PERBAIKAN: Menggunakan tanda hubung (-) sesuai definisi di web.php
          return redirect()->route('admin.stock-mutations.index')->with('success', 'Permintaan mutasi disetujui dan barang dalam perjalanan.');
    }

    public function show(StockMutation $stockMutation)
    {
        // PERBAIKAN: Memuat relasi 'lokasiAsal' dan 'lokasiTujuan'
        $stockMutation->load(['part', 'lokasiAsal', 'lokasiTujuan', 'rakAsal', 'createdBy', 'approvedBy']);

        return view('admin.stock_mutations.show', compact('stockMutation'));
    }

    public function reject(Request $request, StockMutation $stockMutation)
    {
        // PERBAIKAN: Menggunakan nama Gate yang baru
        $this->authorize('approve-stock-transaction', $stockMutation);

        $request->validate([
            'rejection_reason' => 'required|string|min:10',
        ]);

        if ($stockMutation->status !== 'PENDING_APPROVAL') {
            return back()->with('error', 'Permintaan ini sudah diproses.');
        }

        $stockMutation->status = 'REJECTED';
        $stockMutation->rejection_reason = $request->rejection_reason;
        $stockMutation->approved_by = Auth::id();
        $stockMutation->approved_at = now();
        $stockMutation->save();

        return redirect()->route('admin.stock-mutations.index')->with('success', 'Permintaan mutasi stok telah ditolak.');
    }

    public function getPartsWithStock(Lokasi $lokasi)
    {
        $partIds = InventoryBatch::where('gudang_id', $lokasi->id)
            ->where('quantity', '>', 0)
            ->pluck('part_id')
            ->unique();

        $parts = Part::whereIn('id', $partIds)->orderBy('nama_part')->get();
        return response()->json($parts);
    }

    public function getPartStockDetails(Request $request)
    {
        $request->validate([
            'gudang_id' => 'required|exists:lokasi,id',
            'part_id' => 'required|exists:parts,id',
        ]);

        $totalStock = InventoryBatch::where('gudang_id', $request->gudang_id)
            ->where('part_id', $request->part_id)
            ->sum('quantity');

        return response()->json(['total_stock' => $totalStock]);
    }
}
