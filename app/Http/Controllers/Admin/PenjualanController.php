<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Penjualan;
use App\Models\Konsumen;
use App\Models\Lokasi;
use App\Models\User;
use App\Models\Part;
use App\Models\InventoryBatch;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\DiscountService; // Asumsi Anda masih menggunakan ini

class PenjualanController extends Controller
{
    protected $discountService;

    public function __construct(DiscountService $discountService)
    {
        $this->discountService = $discountService;
    }

    public function index()
    {
        $this->authorize('view-sales');

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $query = Penjualan::with(['konsumen', 'sales', 'lokasi'])->latest();

        if (!$user->hasRole(['SA', 'PIC', 'MA'])) {
            $query->where('lokasi_id', $user->lokasi_id);
        }

        $penjualans = $query->get();
        return view('admin.penjualans.index', compact('penjualans'));
    }

    // Method create() ini sudah benar dari perbaikan sebelumnya
    public function create()
    {
        $this->authorize('create-sale');

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $konsumens = Konsumen::where('is_active', true)->orderBy('nama_konsumen')->get();

        $userLokasi = null;     // Untuk staf biasa
        $allLokasi = collect(); // Untuk SA/PIC

        if ($user->hasRole(['SA', 'PIC'])) {
            $allLokasi = Lokasi::where('is_active', true)->orderBy('nama_lokasi')->get();
        }
        elseif ($user->lokasi_id) {
            $userLokasi = Lokasi::find($user->lokasi_id);
        }

        if (!$user->hasRole(['SA', 'PIC']) && !$userLokasi) {
            return redirect()->route('admin.home')->with('error', 'Akun Anda tidak terasosiasi dengan lokasi yang valid untuk membuat penjualan.');
        }
        if ($user->hasRole(['SA', 'PIC']) && $allLokasi->isEmpty()) {
            return redirect()->route('admin.home')->with('error', 'Tidak ada lokasi penjualan aktif yang terdaftar di sistem.');
        }

        return view('admin.penjualans.create', compact('konsumens', 'userLokasi', 'allLokasi'));
    }

    public function store(Request $request)
    {
        $this->authorize('create-sale');

        $validated = $request->validate([
            'lokasi_id' => 'required|exists:lokasi,id',
            'konsumen_id' => 'required|exists:konsumens,id',
            'tanggal_jual' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.part_id' => 'required|exists:parts,id',
            // 'batch_id' sudah tidak ada
            'items.*.qty_jual' => 'required|integer|min:1',
        ], [
            'items.required' => 'Setidaknya satu item harus ditambahkan ke penjualan.',
            'items.*.part_id.required' => 'Part harus dipilih pada semua baris item.',
            'items.*.qty_jual.min' => 'Jumlah jual harus minimal 1 pada semua baris item.',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Otorisasi (sudah benar dari perbaikan sebelumnya)
        if (!$user->hasRole(['SA', 'PIC']) && $user->lokasi_id != $validated['lokasi_id']) {
            abort(403, 'Aksi tidak diizinkan. Anda hanya dapat membuat penjualan dari lokasi Anda.');
        }

        DB::beginTransaction();
        try {
            $konsumen = Konsumen::find($validated['konsumen_id']);
            $lokasiId = $validated['lokasi_id'];
            $totalSubtotalServer = 0;
            $totalDiskonServer = 0; // Tetap hitung diskon untuk laporan

            $penjualan = Penjualan::create([
                'nomor_faktur' => Penjualan::generateNomorFaktur(),
                'tanggal_jual' => $validated['tanggal_jual'],
                'lokasi_id' => $lokasiId,
                'konsumen_id' => $validated['konsumen_id'],
                'sales_id' => $user->id,
                'created_by' => $user->id,
                // Total akan di-update di akhir
            ]);

            // Agregasi item (jika user memilih part yang sama di baris berbeda)
            $aggregatedItems = [];
            foreach ($validated['items'] as $item) {
                $partId = $item['part_id'];
                $qty = (int)$item['qty_jual'];
                if (isset($aggregatedItems[$partId])) {
                    $aggregatedItems[$partId]['qty_jual'] += $qty;
                } else {
                    $aggregatedItems[$partId] = [
                        'part_id' => $partId,
                        'qty_jual' => $qty,
                        'part_object' => Part::find($partId) // Ambil objek part sekali
                    ];
                }
            }

            // Proses setiap part yang sudah diagregasi
            foreach ($aggregatedItems as $partId => $item) {
                $part = $item['part_object'];
                $qtyDiminta = $item['qty_jual'];
                $sisaQtyDiminta = $qtyDiminta;

                // Ambil semua batch untuk part ini di lokasi ini (FIFO)
                $batches = InventoryBatch::where('part_id', $partId)
                    ->where('lokasi_id', $lokasiId)
                    ->where('quantity', '>', 0)
                    ->with(['rak', 'receivingDetail.receiving']) // Load relasi
                    ->get()
                    ->sortBy(function($batch) { // Sort by FIFO
                        if ($batch->receivingDetail && $batch->receivingDetail->receiving) {
                            return $batch->receivingDetail->receiving->tanggal_terima;
                        }
                        return $batch->created_at; // Fallback ke created_at batch
                    });

                // Cek stok total
                $totalStokTersedia = $batches->sum('quantity');
                if ($totalStokTersedia < $qtyDiminta) {
                    throw new \Exception("Stok tidak mencukupi untuk part '{$part->nama_part}'. Diminta: {$qtyDiminta}, Tersedia: {$totalStokTersedia}");
                }

                // Ambil harga jual dari part
                $finalPrice = $part->harga_satuan;

                // Hitung diskon (jika ada) untuk laporan
                $discountResult = $this->discountService->calculateSalesDiscount($part, $konsumen, $part->harga_satuan);
                $totalDiskonServer += ($part->harga_satuan - $discountResult['final_price']) * $qtyDiminta;


                // Loop per batch (FIFO) untuk memenuhi kuantitas
                foreach ($batches as $batch) {
                    if ($sisaQtyDiminta <= 0) break; // Kebutuhan sudah terpenuhi

                    $qtyAmbil = min($sisaQtyDiminta, $batch->quantity);

                    $itemSubtotal = $finalPrice * $qtyAmbil;
                    $totalSubtotalServer += $itemSubtotal;

                    $stokSebelumBatch = $batch->quantity;

                    // Kurangi stok batch
                    $batch->decrement('quantity', $qtyAmbil);

                    // Buat PenjualanDetail untuk SETIAP batch yang diambil
                    $penjualan->details()->create([
                        'part_id' => $part->id,
                        'rak_id' => $batch->rak_id,
                        'qty_jual' => $qtyAmbil,
                        'harga_jual' => $finalPrice,
                        'subtotal' => $itemSubtotal,
                        // Anda mungkin perlu menyimpan referensi batch_id di sini
                        // 'inventory_batch_id' => $batch->id
                    ]);

                    // Buat StockMovement untuk SETIAP batch
                    $penjualan->stockMovements()->create([
                        'part_id' => $part->id,
                        'lokasi_id' => $lokasiId,
                        'rak_id' => $batch->rak_id,
                        'jumlah' => -$qtyAmbil,
                        'stok_sebelum' => $stokSebelumBatch, // Stok batch sebelum
                        'stok_sesudah' => $batch->quantity, // Stok batch sesudah
                        'user_id' => $user->id,
                        'keterangan' => 'Penjualan via Faktur #' . $penjualan->nomor_faktur,
                         // 'referensi_type' & 'referensi_id' di-handle oleh relasi morphMany
                    ]);

                    $sisaQtyDiminta -= $qtyAmbil;
                }
            }

            // Hapus batch yang stoknya 0
            InventoryBatch::where('quantity', '<=', 0)->delete();

            $pajak = 0; // Pajak 0
            $totalHarga = $totalSubtotalServer; // Total = Subtotal

            // Update total di dokumen penjualan
            $penjualan->update([
                'subtotal' => $totalSubtotalServer,
                'total_diskon' => $totalDiskonServer, // Simpan diskon untuk laporan
                'pajak' => $pajak,
                'total_harga' => $totalHarga,
            ]);

            DB::commit();
            return redirect()->route('admin.penjualans.show', $penjualan)->with('success', 'Transaksi penjualan berhasil disimpan.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage())->withInput();
        }
    }

    public function show(Penjualan $penjualan)
    {
        $this->authorize('view-sales');
        $penjualan->load(['konsumen', 'lokasi', 'sales', 'details.part', 'details.rak']);
        return view('admin.penjualans.show', compact('penjualan'));
    }
    /**
     * @param  \App\Models\Penjualan  $penjualan
     * @return \Illuminate\View\View
     */
    public function print(Penjualan $penjualan)
    {
        // Otorisasi, sesuaikan jika ada gate khusus untuk print
        // Kita gunakan 'view-sales' untuk sementara
        $this->authorize('view-sales');

        // Load relasi yang diperlukan untuk mencetak faktur
        $penjualan->load([
            'konsumen',
            'lokasi',
            'sales',
            'details.part',
            'details.rak'
        ]);

        // Kembalikan view 'print.blade.php' dengan data penjualan
        return view('admin.penjualans.print', compact('penjualan'));
    }

    // --- API Methods ---

    // API ini (getPartsByLokasi) masih SANGAT diperlukan untuk mengisi dropdown
    public function getPartsByLokasi(Lokasi $lokasi)
    {
        $this->authorize('create-sale');
        $parts = Part::whereHas('inventoryBatches', function ($query) use ($lokasi) {
            $query->where('lokasi_id', $lokasi->id)->where('quantity', '>', 0);
        })
        ->withSum(['inventoryBatches' => function ($query) use ($lokasi) {
            $query->where('lokasi_id', $lokasi->id);
        }], 'quantity')
        ->orderBy('nama_part')
        ->get()
        ->map(function($part) {
            return [
                'id' => $part->id,
                'kode_part' => $part->kode_part,
                'nama_part' => $part->nama_part,
                'total_stock' => (int) $part->inventory_batches_sum_quantity,
                'harga_satuan' => $part->harga_satuan, // Kirim harga satuan
            ];
        });

        return response()->json($parts);
    }

    public function getFifoBatches(Request $request)
    {
        // ... (Logika ini sekarang ada di method store) ...
        // Jika tidak ada tempat lain yang memakai, ini bisa dihapus
        // Jika masih dipakai, biarkan saja
         $this->authorize('create-sale');
        $validated = $request->validate([
            'part_id' => 'required|exists:parts,id',
            'lokasi_id' => 'required|exists:lokasi,id',
        ]);

        $batches = InventoryBatch::where('part_id', $validated['part_id'])
            ->where('lokasi_id', $validated['lokasi_id'])
            ->where('quantity', '>', 0)
            ->with(['rak', 'receivingDetail.receiving'])
            ->get()
            ->sortBy(function($batch) {
                if ($batch->receivingDetail && $batch->receivingDetail->receiving) {
                    return $batch->receivingDetail->receiving->tanggal_terima;
                }
                return $batch->created_at;
            });

        return response()->json($batches->values()->all());
    }

    public function calculateDiscount(Request $request)
    {
         // ... (Logika ini sekarang ada di method store) ...
         // Jika tidak ada tempat lain yang memakai, ini bisa dihapus
        $request->validate(['part_id' => 'required|exists:parts,id']);
        try {
            $part = Part::findOrFail($request->part_id);
            return response()->json(['success' => true, 'data' => [
                'original_price' => $part->harga_satuan,
                'final_price' => $part->harga_satuan,
                'applied_discounts' => []
            ]]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal mengambil harga: ' . $e->getMessage()], 500);
        }
    }
}
