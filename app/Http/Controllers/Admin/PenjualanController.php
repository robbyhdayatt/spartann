<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Penjualan;
use App\Models\Konsumen;
use App\Models\Lokasi; // DIUBAH DARI GUDANG
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

        // Filter penjualan berdasarkan lokasi user, kecuali untuk SA/PIC/MA
        if (!$user->hasRole(['SA', 'PIC', 'MA'])) {
            $query->where('gudang_id', $user->gudang_id);
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

        // Lokasi penjualan ditentukan oleh lokasi user yang login
        $lokasi = Lokasi::find($user->gudang_id);

        if (!$lokasi) {
            return redirect()->route('admin.home')->with('error', 'Anda tidak terasosiasi dengan lokasi manapun.');
        }

        return view('admin.penjualans.create', compact('konsumens', 'lokasi'));
    }

    public function store(Request $request)
    {
        $this->authorize('create-sale');

        $validated = $request->validate([
            'gudang_id' => 'required|exists:lokasi,id', // DIUBAH
            'konsumen_id' => 'required|exists:konsumens,id',
            'tanggal_jual' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.part_id' => 'required|exists:parts,id',
            'items.*.batch_id' => 'required|exists:inventory_batches,id',
            'items.*.qty_jual' => 'required|integer|min:1',
        ]);
        
        /** @var \App\Models\User $user */
        $user = Auth::user();
        // Validasi tambahan: pastikan user hanya bisa menjual dari lokasinya sendiri
        if ($user->gudang_id != $validated['gudang_id']) {
            abort(403, 'Aksi tidak diizinkan. Anda hanya dapat membuat penjualan dari lokasi Anda.');
        }

        DB::beginTransaction();
        try {
            $konsumen = Konsumen::find($validated['konsumen_id']);
            $totalSubtotalServer = 0;
            $totalDiskonServer = 0;

            $penjualan = Penjualan::create([
                'nomor_faktur' => Penjualan::generateNomorFaktur(),
                'tanggal_jual' => $validated['tanggal_jual'],
                'gudang_id' => $validated['gudang_id'],
                'konsumen_id' => $validated['konsumen_id'],
                'sales_id' => $user->id,
                'created_by' => $user->id, // Tambahkan created_by jika belum ada
            ]);

            foreach ($validated['items'] as $item) {
                $part = Part::find($item['part_id']);
                $qty = (int)$item['qty_jual'];
                $batch = InventoryBatch::findOrFail($item['batch_id']);

                if ($batch->quantity < $qty) {
                    throw new \Exception("Stok di batch untuk part '{$part->nama_part}' tidak mencukupi.");
                }

                $discountResult = $this->discountService->calculateSalesDiscount($part, $konsumen, $part->harga_jual_default);
                $finalPrice = $discountResult['final_price'];
                $itemSubtotal = $finalPrice * $qty;

                $totalSubtotalServer += $itemSubtotal;
                $totalDiskonServer += ($part->harga_jual_default - $finalPrice) * $qty;

                $stokSebelum = $batch->quantity;
                $batch->decrement('quantity', $qty);

                $penjualan->details()->create([
                    'part_id' => $part->id,
                    'rak_id' => $batch->rak_id,
                    'qty_jual' => $qty,
                    'harga_jual' => $finalPrice,
                    'subtotal' => $itemSubtotal,
                ]);

                // Gunakan relasi untuk membuat stock movement
                $penjualan->stockMovements()->create([
                    'part_id' => $part->id,
                    'gudang_id' => $penjualan->gudang_id,
                    'rak_id' => $batch->rak_id,
                    'jumlah' => -$qty,
                    'stok_sebelum' => $stokSebelum,
                    'stok_sesudah' => $batch->quantity,
                    'user_id' => $user->id,
                    'keterangan' => 'Penjualan via Faktur #' . $penjualan->nomor_faktur,
                ]);
            }

            InventoryBatch::where('quantity', '<=', 0)->delete();

            $pajak = ($request->use_ppn == 1) ? $totalSubtotalServer * 0.11 : 0;
            $totalHarga = $totalSubtotalServer + $pajak;

            $penjualan->update([
                'subtotal' => $totalSubtotalServer,
                'total_diskon' => $totalDiskonServer,
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
        $penjualan->load(['konsumen', 'lokasi', 'sales', 'details.part', 'details.rak']); // DIUBAH GUDANG -> LOKASI
        return view('admin.penjualans.show', compact('penjualan'));
    }

    // --- API Methods ---
    public function getPartsByLokasi(Lokasi $lokasi) // DIUBAH
    {
        $this->authorize('create-sale');
        $parts = Part::whereHas('inventoryBatches', function ($query) use ($lokasi) {
            $query->where('gudang_id', $lokasi->id)->where('quantity', '>', 0);
        })
        ->withSum(['inventoryBatches' => function ($query) use ($lokasi) {
            $query->where('gudang_id', $lokasi->id);
        }], 'quantity')
        ->orderBy('nama_part')
        ->get()
        ->map(function($part) {
            return [
                'id' => $part->id,
                'kode_part' => $part->kode_part,
                'nama_part' => $part->nama_part,
                'total_stock' => (int) $part->inventory_batches_sum_quantity,
            ];
        });

        return response()->json($parts);
    }

    public function getFifoBatches(Request $request)
    {
        $this->authorize('create-sale');
        $validated = $request->validate([
            'part_id' => 'required|exists:parts,id',
            'gudang_id' => 'required|exists:lokasi,id', // DIUBAH
        ]);

        $batches = InventoryBatch::where('part_id', $validated['part_id'])
            ->where('gudang_id', $validated['gudang_id'])
            ->where('quantity', '>', 0)
            ->with(['rak', 'receivingDetail.receiving'])
            ->get()
            // Urutkan berdasarkan tanggal dibuatnya batch (jika tidak ada referensi receiving)
            // atau tanggal terima barang jika ada referensi.
            ->sortBy(function($batch) {
                if ($batch->receivingDetail && $batch->receivingDetail->receiving) {
                    return $batch->receivingDetail->receiving->tanggal_terima->format('Y-m-d H:i:s');
                }
                return $batch->created_at->format('Y-m-d H:i:s');
            });
            
        return response()->json($batches->values()->all());
    }

    public function calculateDiscount(Request $request)
    {
        $request->validate(['part_id' => 'required|exists:parts,id', 'konsumen_id' => 'required|exists:konsumens,id']);
        try {
            $part = Part::findOrFail($request->part_id);
            $konsumen = Konsumen::findOrFail($request->konsumen_id);
            $basePrice = $part->harga_jual_default;
            $discountResult = $this->discountService->calculateSalesDiscount($part, $konsumen, $basePrice);
            return response()->json(['success' => true, 'data' => $discountResult]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menghitung diskon: ' . $e->getMessage()], 500);
        }
    }
}
