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
use App\Services\DiscountService;

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

    public function create()
    {
        $this->authorize('create-sale');

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $konsumens = Konsumen::where('is_active', true)->orderBy('nama_konsumen')->get();
        $lokasi = Lokasi::find($user->lokasi_id);

        if (!$lokasi) {
            return redirect()->route('admin.home')->with('error', 'Anda tidak terasosiasi dengan lokasi manapun.');
        }

        return view('admin.penjualans.create', compact('konsumens', 'lokasi'));
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
            'items.*.batch_id' => 'required|exists:inventory_batches,id',
            'items.*.qty_jual' => 'required|integer|min:1',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        if ($user->lokasi_id != $validated['lokasi_id']) {
            abort(403, 'Aksi tidak diizinkan. Anda hanya dapat membuat penjualan dari lokasi Anda.');
        }

        DB::beginTransaction();
        try {
            $konsumen = Konsumen::find($validated['konsumen_id']);
            $totalSubtotalServer = 0;
            // Total diskon tidak lagi relevan karena kita pakai harga_satuan
            $totalDiskonServer = 0;

            $penjualan = Penjualan::create([
                'nomor_faktur' => Penjualan::generateNomorFaktur(),
                'tanggal_jual' => $validated['tanggal_jual'],
                'lokasi_id' => $validated['lokasi_id'],
                'konsumen_id' => $validated['konsumen_id'],
                'sales_id' => $user->id,
                'created_by' => $user->id,
            ]);

            foreach ($validated['items'] as $item) {
                $part = Part::find($item['part_id']);
                $qty = (int)$item['qty_jual'];
                $batch = InventoryBatch::findOrFail($item['batch_id']);

                if ($batch->quantity < $qty) {
                    throw new \Exception("Stok di batch untuk part '{$part->nama_part}' tidak mencukupi.");
                }

                // ++ PERUBAHAN UTAMA: Gunakan 'harga_satuan' langsung sebagai harga jual ++
                $finalPrice = $part->harga_satuan;
                $itemSubtotal = $finalPrice * $qty;
                $totalSubtotalServer += $itemSubtotal;

                // Kalkulasi diskon (jika ada) hanya untuk laporan, tidak mempengaruhi total
                $discountResult = $this->discountService->calculateSalesDiscount($part, $konsumen, $part->harga_satuan);
                $totalDiskonServer += ($part->harga_satuan - $discountResult['final_price']) * $qty;

                $stokSebelum = $batch->quantity;
                $batch->decrement('quantity', $qty);

                $penjualan->details()->create([
                    'part_id' => $part->id,
                    'rak_id' => $batch->rak_id,
                    'qty_jual' => $qty,
                    'harga_jual' => $finalPrice,
                    'subtotal' => $itemSubtotal,
                ]);

                $penjualan->stockMovements()->create([
                    'part_id' => $part->id,
                    'lokasi_id' => $penjualan->lokasi_id,
                    'rak_id' => $batch->rak_id,
                    'jumlah' => -$qty,
                    'stok_sebelum' => $stokSebelum,
                    'stok_sesudah' => $batch->quantity,
                    'user_id' => $user->id,
                    'keterangan' => 'Penjualan via Faktur #' . $penjualan->nomor_faktur,
                ]);
            }

            InventoryBatch::where('quantity', '<=', 0)->delete();

            // ++ PERUBAHAN: Pajak di-nol-kan dan total disesuaikan ++
            $pajak = 0;
            $totalHarga = $totalSubtotalServer;

            $penjualan->update([
                'subtotal' => $totalSubtotalServer,
                'total_diskon' => $totalDiskonServer, // Tetap simpan diskon untuk laporan
                'pajak' => $pajak, // Pajak akan selalu 0
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

    // --- API Methods ---
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
                // ++ PERUBAHAN: Kirim harga_satuan ke frontend ++
                'harga_satuan' => $part->harga_satuan,
            ];
        });

        return response()->json($parts);
    }

    public function getFifoBatches(Request $request)
    {
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

    // ++ PERUBAHAN: Fungsi ini sekarang hanya mengembalikan harga_satuan (untuk konsistensi) ++
    public function calculateDiscount(Request $request)
    {
        $request->validate(['part_id' => 'required|exists:parts,id']);
        try {
            $part = Part::findOrFail($request->part_id);
            // Anda bisa tambahkan lagi logika diskon di sini jika diperlukan di masa depan
            // Untuk sekarang, kita kembalikan harga asli dan harga final yang sama
            return response()->json(['success' => true, 'data' => [
                'original_price' => $part->harga_satuan,
                'final_price' => $part->harga_satuan, // Harga final sama dengan harga satuan
                'applied_discounts' => []
            ]]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal mengambil harga: ' . $e->getMessage()], 500);
        }
    }
}
