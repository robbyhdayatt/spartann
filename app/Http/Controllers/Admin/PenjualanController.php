<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Models\Konsumen;
use App\Models\Lokasi;
use App\Models\Barang; // Ganti Part
use App\Models\InventoryBatch;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PenjualanController extends Controller
{
    public function index()
    {
        $this->authorize('view-sales');
        $user = Auth::user();

        $query = Penjualan::with(['konsumen', 'sales', 'lokasi', 'details.barang'])->latest();

        // Filter Lokasi: Dealer hanya lihat data mereka sendiri
        if (!$user->hasRole(['SA', 'PIC', 'MA'])) {
            if ($user->lokasi_id) {
                $query->where('lokasi_id', $user->lokasi_id);
            } else {
                $query->whereRaw('1 = 0'); // Blokir jika tidak punya lokasi
            }
        }

        $penjualans = $query->paginate(15);
        return view('admin.penjualans.index', compact('penjualans'));
    }

    public function create()
    {
        $this->authorize('create-sale');
        $user = Auth::user();

        // Pastikan user punya lokasi untuk jualan
        if (!$user->lokasi_id && !$user->hasRole(['SA', 'PIC'])) {
             return redirect()->route('admin.home')->with('error', 'Akun Anda tidak terasosiasi dengan lokasi penjualan.');
        }

        $konsumens = Konsumen::orderBy('nama_konsumen')->get();

        // Lokasi Penjualan otomatis ikut user
        $lokasi = $user->lokasi ?? Lokasi::first(); // Fallback untuk SA

        return view('admin.penjualans.create', compact('konsumens', 'lokasi'));
    }

    public function store(Request $request)
    {
        $this->authorize('create-sale');
        $user = Auth::user();

        $validated = $request->validate([
            'konsumen_id' => 'required|exists:konsumens,id',
            'tanggal_jual' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.barang_id' => 'required|exists:barangs,id',
            'items.*.qty' => 'required|integer|min:1',
        ]);

        $lokasiId = $user->lokasi_id;
        if (!$lokasiId && $user->hasRole(['SA', 'PIC'])) {
             // Jika SA login tanpa lokasi, default ke Pusat (atau bisa dibuat dropdown pilihan)
             $lokasiId = Lokasi::where('tipe', 'PUSAT')->first()->id;
        }

        DB::beginTransaction();
        try {
            // 1. Buat Header Penjualan
            $penjualan = Penjualan::create([
                'nomor_faktur' => $this->generateNomorFaktur($lokasiId),
                'tanggal_jual' => $validated['tanggal_jual'],
                'lokasi_id'    => $lokasiId,
                'konsumen_id'  => $validated['konsumen_id'],
                'sales_id'     => $user->id, // Sales yg login
                'created_by'   => $user->id,
                'status'       => 'COMPLETED', // Langsung selesai (POS)
                'subtotal'     => 0, // Nanti diupdate
                'total_harga'  => 0,
                'pajak'        => 0,
            ]);

            $totalSubtotal = 0;

            // 2. Loop Item & Kurangi Stok (FIFO)
            foreach ($validated['items'] as $item) {
                $barang = Barang::find($item['barang_id']);
                $qtyJual = $item['qty'];

                // Gunakan harga 'retail'
                $hargaJual = $barang->retail;
                $subtotalItem = $hargaJual * $qtyJual;
                $totalSubtotal += $subtotalItem;

                // Cek Stok Total
                $stokTersedia = InventoryBatch::where('barang_id', $barang->id)
                                              ->where('lokasi_id', $lokasiId)
                                              ->sum('quantity');

                if ($stokTersedia < $qtyJual) {
                    throw new \Exception("Stok untuk {$barang->part_name} tidak mencukupi. Tersedia: {$stokTersedia}");
                }

                // --- LOGIKA PENGURANGAN STOK (FIFO) ---
                $batches = InventoryBatch::where('barang_id', $barang->id)
                                         ->where('lokasi_id', $lokasiId)
                                         ->where('quantity', '>', 0)
                                         ->orderBy('created_at', 'asc') // Batch terlama keluar duluan
                                         ->get();

                $sisaQtyToCut = $qtyJual;

                foreach ($batches as $batch) {
                    if ($sisaQtyToCut <= 0) break;

                    $potong = min($batch->quantity, $sisaQtyToCut);

                    // Update Batch
                    $batch->decrement('quantity', $potong);

                    // Catat Pergerakan Stok (Movement)
                    StockMovement::create([
                        'barang_id'      => $barang->id,
                        'lokasi_id'      => $lokasiId,
                        'rak_id'         => $batch->rak_id, // Ambil dari batch
                        'jumlah'         => -$potong, // Negatif karena keluar
                        'stok_sebelum'   => $batch->quantity + $potong,
                        'stok_sesudah'   => $batch->quantity,
                        'referensi_type' => get_class($penjualan),
                        'referensi_id'   => $penjualan->id,
                        'keterangan'     => "Penjualan Faktur #{$penjualan->nomor_faktur}",
                        'user_id'        => $user->id,
                    ]);

                    $sisaQtyToCut -= $potong;

                    // Hapus batch jika 0 (Opsional, biar tabel tidak penuh)
                    // if ($batch->quantity == 0) $batch->delete();
                }
                // --- AKHIR FIFO ---

                // 3. Simpan Detail Penjualan
                $penjualan->details()->create([
                    'barang_id'  => $barang->id,
                    'qty_jual'   => $qtyJual,
                    'harga_jual' => $hargaJual,
                    'subtotal'   => $subtotalItem,
                ]);
            }

            // 4. Update Total Header
            $penjualan->update([
                'subtotal'    => $totalSubtotal,
                'total_harga' => $totalSubtotal, // + Pajak jika ada
            ]);

            DB::commit();
            return redirect()->route('admin.penjualans.show', $penjualan->id)->with('success', 'Transaksi berhasil disimpan.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menyimpan: ' . $e->getMessage())->withInput();
        }
    }

    public function show(Penjualan $penjualan)
    {
        $this->authorize('view-sales');
        // Load relasi details.barang (bukan details.part)
        $penjualan->load(['konsumen', 'lokasi', 'sales', 'details.barang']);
        return view('admin.penjualans.show', compact('penjualan'));
    }

    // Helper Generate Nomor Faktur (Format: INV/KODELOKASI/TGL/URUT)
    private function generateNomorFaktur($lokasiId)
    {
        $lokasi = Lokasi::find($lokasiId);
        $kodeLokasi = $lokasi ? $lokasi->kode_lokasi : 'GEN';
        $date = now()->format('ymd');

        $count = Penjualan::where('lokasi_id', $lokasiId)
                          ->whereDate('created_at', today())
                          ->count();
        $sequence = str_pad($count + 1, 4, '0', STR_PAD_LEFT);

        return "INV/{$kodeLokasi}/{$date}/{$sequence}";
    }

    // API: Ambil barang beserta stoknya untuk dropdown
public function getBarangItems(Request $request)
    {
         $this->authorize('create-sale');
         $user = Auth::user();

         // 1. Ambil lokasi dari parameter request (jika ada), jika tidak gunakan lokasi user
         $lokasiId = $request->input('lokasi_id') ?? $user->lokasi_id;

         // 2. Fallback untuk SA jika tidak ada parameter dan tidak ada lokasi user
         if (!$lokasiId && $user->hasRole(['SA', 'PIC'])) {
             $lokasiPusat = Lokasi::where('tipe', 'PUSAT')->first();
             $lokasiId = $lokasiPusat ? $lokasiPusat->id : null;
         }

         if (!$lokasiId) return response()->json([]);

         // 3. Query menggunakan LEFT JOIN agar barang dengan stok 0 tetap tampil
         $barangs = Barang::select(
                 'barangs.id',
                 'barangs.part_name',
                 'barangs.part_code',
                 'barangs.retail',
                 'barangs.merk'
             )
             ->leftJoin('inventory_batches', function($join) use ($lokasiId) {
                 $join->on('barangs.id', '=', 'inventory_batches.barang_id')
                      ->where('inventory_batches.lokasi_id', '=', $lokasiId);
             })
             // Hapus where quantity > 0 agar item stok 0 tetap muncul (opsional)
             // ->where('inventory_batches.quantity', '>', 0)
             ->groupBy('barangs.id', 'barangs.part_name', 'barangs.part_code', 'barangs.retail', 'barangs.merk')
             ->selectRaw('COALESCE(SUM(inventory_batches.quantity), 0) as total_stok')
             ->orderBy('barangs.part_name')
             ->get();

         // Tambahkan properti text untuk Select2
         $results = $barangs->map(function($item) {
             $item->text = $item->part_name . ' (' . $item->part_code . ')';
             if ($item->merk) $item->text .= ' - ' . $item->merk;
             // Tambahkan info stok di label
             $item->text .= ' [Stok: ' . $item->total_stok . ']';
             return $item;
         });

         return response()->json($results);
    }
}
