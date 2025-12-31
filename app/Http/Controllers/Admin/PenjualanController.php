<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Models\Konsumen;
use App\Models\Lokasi;
use App\Models\Barang;
use App\Models\InventoryBatch;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PenjualanController extends Controller
{

    public function index()
    {
        $this->authorize('view-sales');
        $user = Auth::user();
        $query = Penjualan::with(['konsumen', 'sales', 'lokasi', 'details.barang'])->latest();

        if (!$user->hasRole(['SA', 'PIC', 'MA', 'ASD'])) {
            if ($user->lokasi_id) {
                $query->where('lokasi_id', $user->lokasi_id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }
        $penjualans = $query->paginate(15);
        return view('admin.penjualans.index', compact('penjualans'));
    }

    public function create()
    {
        $this->authorize('create-sale');
        $user = Auth::user();

        if (!$user->lokasi_id && !$user->hasRole(['SA', 'PIC'])) {
             return redirect()->route('admin.home')->with('error', 'Akun Anda tidak terasosiasi dengan lokasi penjualan.');
        }

        $lokasi = $user->lokasi ?? Lokasi::first();
        $today = now()->format('Y-m-d');

        return view('admin.penjualans.create', compact('lokasi', 'today'));
    }

    public function store(Request $request)
    {
        $this->authorize('create-sale');
        $user = Auth::user();

        // Validasi
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'tipe_konsumen' => 'required|in:BENGKEL,RETAIL',
            'alamat'        => 'nullable|string|max:500',
            'telepon'       => 'nullable|string|max:20',
            'tanggal_jual'  => 'required|date',
            'items'         => 'required|array|min:1',
            'items.*.barang_id' => 'required|exists:barangs,id',
            'items.*.qty'   => 'required|integer|min:1',
            'nama_diskon'   => 'nullable|string|max:100',
            'nilai_diskon'  => 'nullable|numeric|min:0',
            'ppn_check'     => 'nullable|in:1,0',
        ]);

        $lokasiId = $user->lokasi_id;
        // Fallback jika user pusat (untuk testing)
        if (!$lokasiId && $user->hasRole(['SA', 'PIC', 'IT', 'ASD'])) {
             $lokasiId = Lokasi::where('tipe', 'PUSAT')->first()->id;
        }

        DB::beginTransaction();
        try {
            // 1. Handle Konsumen (Create or Find)
            $konsumen = Konsumen::where('nama_konsumen', $validated['customer_name'])->first();
            if (!$konsumen) {
                $kodeKonsumen = 'CST' . now()->format('ymdHis') . rand(100, 999);
                $konsumen = Konsumen::create([
                    'nama_konsumen' => $validated['customer_name'],
                    'kode_konsumen' => $kodeKonsumen,
                    'tipe_konsumen' => $validated['tipe_konsumen'],
                    'alamat'        => $validated['alamat'] ?? '-',
                    'no_hp'         => $validated['telepon'] ?? '-',
                ]);
            } else {
                $konsumen->update([
                    'tipe_konsumen' => $validated['tipe_konsumen'],
                    'alamat'        => $validated['alamat'] ?? $konsumen->alamat,
                    'no_hp'         => $validated['telepon'] ?? $konsumen->no_hp,
                ]);
            }

            // Persiapan Data Header
            $subtotalGlobal = 0;
            $diskon = $request->input('nilai_diskon', 0);
            $isPpn = $request->has('ppn_check');

            // 2. Buat Header Penjualan Dulu
            $penjualan = Penjualan::create([
                'nomor_faktur' => $this->generateNomorFaktur($lokasiId),
                'tanggal_jual' => $validated['tanggal_jual'],
                'lokasi_id'    => $lokasiId,
                'konsumen_id'  => $konsumen->id, 
                'sales_id'     => $user->id,
                'created_by'   => $user->id,
                'status'       => 'COMPLETED',
                'keterangan_diskon' => $request->nama_diskon,
                'diskon'       => $diskon, 
                'total_diskon' => $diskon,
                'subtotal'     => 0, // Nanti diupdate
                'pajak'        => 0, // Nanti diupdate
                'total_harga'  => 0, // Nanti diupdate
            ]);

            // 3. Loop Item & Kurangi Stok (FIFO + SPLIT RAK)
            foreach ($validated['items'] as $item) {
                $barang = Barang::find($item['barang_id']);
                $qtyDiminta = $item['qty'];
                $hargaJual = $barang->retail; 

                // Hitung total harga item ini (untuk menambah subtotal global)
                $subtotalItemGlobal = $hargaJual * $qtyDiminta;
                $subtotalGlobal += $subtotalItemGlobal;

                // Ambil Batch Stok (FIFO)
                $batches = InventoryBatch::where('barang_id', $barang->id)
                                         ->where('lokasi_id', $lokasiId)
                                         ->where('quantity', '>', 0)
                                         ->orderBy('created_at', 'asc')
                                         ->lockForUpdate() 
                                         ->get();

                $currentGlobalStock = $batches->sum('quantity');

                if ($currentGlobalStock < $qtyDiminta) {
                    throw new \Exception("Stok {$barang->part_name} tidak cukup. Diminta: {$qtyDiminta}, Ada: {$currentGlobalStock}");
                }

                $sisaQtyToCut = $qtyDiminta;

                // [MODIFIKASI PENTING] Loop Batch & Create Detail Per Batch
                foreach ($batches as $batch) {
                    if ($sisaQtyToCut <= 0) break;

                    // Tentukan berapa yang diambil dari batch/rak ini
                    $potong = min($batch->quantity, $sisaQtyToCut);
                    
                    // 1. Kurangi Fisik Stok
                    $stokSebelum = $batch->quantity;
                    $batch->decrement('quantity', $potong);

                    // 2. Catat Log Pergerakan (Stock Movement)
                    StockMovement::create([
                        'barang_id'      => $barang->id,
                        'lokasi_id'      => $lokasiId,
                        'rak_id'         => $batch->rak_id, 
                        'jumlah'         => -$potong,
                        'stok_sebelum'   => $stokSebelum,
                        'stok_sesudah'   => $stokSebelum - $potong,
                        'referensi_type' => get_class($penjualan),
                        'referensi_id'   => $penjualan->id,
                        'keterangan'     => "Penjualan Faktur #{$penjualan->nomor_faktur}",
                        'user_id'        => $user->id,
                    ]);

                    // 3. [PERBAIKAN UTAMA] Buat Penjualan Detail disini!
                    // Kita simpan detail SESUAI dengan Rak/Batch yang diambil.
                    // Jika ambil dari 2 rak, maka akan create 2 baris detail.
                    $penjualan->details()->create([
                        'barang_id'  => $barang->id,
                        'rak_id'     => $batch->rak_id, // <-- Rak ID Akurat sesuai batch
                        'qty_jual'   => $potong,        // <-- Qty parsial dari rak ini
                        'harga_jual' => $hargaJual,
                        'subtotal'   => $potong * $hargaJual,
                    ]);

                    $sisaQtyToCut -= $potong;
                }
            }

            // 4. Hitung Final (Diskon & PPN)
            $dpp = $subtotalGlobal - $diskon; 
            if ($dpp < 0) $dpp = 0; 

            $nilaiPajak = 0;
            if ($isPpn) {
                $nilaiPajak = $dpp * 0.11; 
            }

            $grandTotal = $dpp + $nilaiPajak;

            // 5. Update Header Penjualan dengan Angka Final
            $penjualan->update([
                'subtotal'    => $subtotalGlobal,
                'diskon'      => $diskon,
                'total_diskon'=> $diskon,
                'pajak'       => $nilaiPajak,
                'total_harga' => $grandTotal,
            ]);

            DB::commit();
            return redirect()->route('admin.penjualans.show', $penjualan->id)
                             ->with('success', 'Transaksi berhasil disimpan.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal: ' . $e->getMessage())->withInput();
        }
    }

    // ... (Method generateNomorFaktur & getBarangItems TETAP SAMA seperti sebelumnya) ...
    private function generateNomorFaktur($lokasiId)
    {
        $lokasi = Lokasi::find($lokasiId);
        $kodeLokasi = $lokasi ? $lokasi->kode_lokasi : 'GEN';
        $date = now()->format('ymd');
        $count = Penjualan::where('lokasi_id', $lokasiId)->whereDate('created_at', today())->count();
        $sequence = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
        return "INV/{$kodeLokasi}/{$date}/{$sequence}";
    }

    public function show(Penjualan $penjualan)
    {
        $this->authorize('view-sales');
        $penjualan->load(['konsumen', 'lokasi', 'sales', 'details.barang']);
        return view('admin.penjualans.show', compact('penjualan'));
    }

    public function getBarangItems(Request $request)
    {
         $this->authorize('create-sale');
         $user = Auth::user();
         $lokasiId = $request->input('lokasi_id') ?? $user->lokasi_id;

         if (!$lokasiId && $user->hasRole(['SA', 'PIC'])) {
             $lokasiPusat = Lokasi::where('tipe', 'PUSAT')->first();
             $lokasiId = $lokasiPusat ? $lokasiPusat->id : null;
         }

         if (!$lokasiId) return response()->json([]);

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
             ->groupBy('barangs.id', 'barangs.part_name', 'barangs.part_code', 'barangs.retail', 'barangs.merk')
             ->selectRaw('COALESCE(SUM(inventory_batches.quantity), 0) as total_stok')
             ->having('total_stok', '>', 0) // Hanya tampilkan yang ada stok
             ->orderBy('barangs.part_name')
             ->get();

         $results = $barangs->map(function($item) {
             $item->text = $item->part_name . ' (' . $item->part_code . ') - Rp ' . number_format($item->retail,0,',','.') . ' [Stok: ' . $item->total_stok . ']';
             // Kirim data harga untuk JS
             $item->price = $item->retail;
             $item->stock = $item->total_stok;
             return $item;
         });

         return response()->json($results);
    }
}