<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockMutation;
use App\Models\InventoryBatch;
use App\Models\Barang; // Ganti Part
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
        // Update relasi part ke barang
        $query = StockMutation::with(['barang', 'lokasiAsal', 'lokasiTujuan', 'createdBy']);

        if (!$user->hasRole(['SA', 'PIC', 'MA', 'ACC'])) {
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
                                ->where('tipe', '!=', 'PUSAT') // ++ PERUBAHAN ++
                                ->orderBy('nama_lokasi')
                                ->get();
        } else {
            // User Dealer hanya bisa memilih lokasinya sendiri (yang pasti bukan PUSAT karena role dealer)
            $lokasiAsal = Lokasi::where('id', $user->lokasi_id)->get();
        }

        // 2. Lokasi Tujuan (Penerima Barang)
        // Tujuan juga tidak boleh PUSAT (Mutasi antar Dealer)
        $lokasiTujuan = Lokasi::where('is_active', true)
                              ->where('tipe', '!=', 'PUSAT') // ++ PERUBAHAN ++
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
            'barang_id' => 'required|exists:barangs,id', // Ganti part_id
            'lokasi_asal_id' => 'required|exists:lokasi,id',
            'lokasi_tujuan_id' => 'required|exists:lokasi,id|different:lokasi_asal_id',
            'jumlah' => 'required|integer|min:1',
            'keterangan' => 'nullable|string',
        ]);

        // Validasi tambahan (Pusat tidak boleh jadi asal/tujuan)
        $lokasiAsal = Lokasi::find($validated['lokasi_asal_id']);
        $lokasiTujuan = Lokasi::find($validated['lokasi_tujuan_id']);

        if ($lokasiAsal->tipe === 'PUSAT' || $lokasiTujuan->tipe === 'PUSAT') {
            return back()->with('error', 'Mutasi yang melibatkan Gudang Pusat tidak diizinkan di menu ini.')->withInput();
        }

        // Cek Stok
        $totalStock = InventoryBatch::where('lokasi_id', $validated['lokasi_asal_id'])
            ->where('barang_id', $validated['barang_id']) // Ganti part_id
            ->sum('quantity');

        if ($totalStock < $validated['jumlah']) {
            return back()->with('error', 'Stok total di lokasi asal tidak mencukupi. Stok tersedia: ' . $totalStock)->withInput();
        }

        $mutationData = $validated;
        $mutationData['nomor_mutasi'] = StockMutation::generateNomorMutasi(); // Pastikan method ini ada di Model
        $mutationData['status'] = 'PENDING_APPROVAL';
        $mutationData['created_by'] = $user->id;
        $mutationData['rak_asal_id'] = null; // Akan diisi saat approve (FIFO)

        StockMutation::create($mutationData);

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

                   // Cek Stok Lagi (untuk memastikan belum berkurang saat pending)
                   $totalStock = InventoryBatch::where('lokasi_id', $stockMutation->lokasi_asal_id)
                         ->where('barang_id', $stockMutation->barang_id) // Ganti part_id
                         ->sum('quantity');

                   if ($totalStock < $jumlahToMutate) {
                       throw new \Exception('Stok saat ini tidak mencukupi. Stok tersedia: ' . $totalStock);
                   }

                   // Ambil Batch FIFO
                   $batches = InventoryBatch::where('lokasi_id', $stockMutation->lokasi_asal_id)
                         ->where('barang_id', $stockMutation->barang_id) // Ganti part_id
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

                       // Catat Movement Keluar
                       StockMovement::create([
                           'barang_id' => $stockMutation->barang_id, // Ganti part_id
                           'lokasi_id' => $stockMutation->lokasi_asal_id,
                           'rak_id' => $batch->rak_id,
                           'jumlah' => -$stokKeluar,
                           'stok_sebelum' => $stokSebelum,
                           'stok_sesudah' => $batch->quantity,
                           'referensi_type' => get_class($stockMutation),
                           'referensi_id' => $stockMutation->id,
                           'keterangan' => 'Mutasi Keluar ke ' . $stockMutation->lokasiTujuan->kode_lokasi,
                           'user_id' => Auth::id(),
                       ]);

                       // Optional: Hapus batch jika 0
                       // if ($batch->quantity <= 0) $batch->delete();
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

          return redirect()->route('admin.stock-mutations.index')->with('success', 'Permintaan mutasi disetujui dan barang dalam perjalanan.');
    }

    public function show(StockMutation $stockMutation)
    {
        $stockMutation->load(['barang', 'lokasiAsal', 'lokasiTujuan', 'rakAsal', 'createdBy', 'approvedBy']);
        return view('admin.stock_mutations.show', compact('stockMutation'));
    }

    public function reject(Request $request, StockMutation $stockMutation)
    {
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

    // API Helper
    public function getPartsWithStock(Lokasi $lokasi)
    {
        $barangIds = InventoryBatch::where('lokasi_id', $lokasi->id)
            ->where('quantity', '>', 0)
            ->pluck('barang_id') // Ganti part_id
            ->unique();

        $barangs = Barang::whereIn('id', $barangIds)->orderBy('part_name')->get();

        // Format data untuk Select2 jika diperlukan di JS, atau kirim raw object
        // Di view create.blade.php Anda sebelumnya pakai JS mapping, jadi kirim raw object oke.
        return response()->json($barangs);
    }

    public function getPartStockDetails(Request $request)
    {
        $request->validate([
            'lokasi_id' => 'required|exists:lokasi,id',
            'barang_id' => 'required|exists:barangs,id', // Ganti part_id
        ]);

        $totalStock = InventoryBatch::where('lokasi_id', $request->lokasi_id)
            ->where('barang_id', $request->barang_id) // Ganti part_id
            ->sum('quantity');

        return response()->json(['total_stock' => $totalStock]);
    }
}
