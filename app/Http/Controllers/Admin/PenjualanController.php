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
use Illuminate\Support\Facades\Validator;

class PenjualanController extends Controller
{
    public function index()
    {
        // [MODIFIKASI] Gate Poin 17: view-sales
        $this->authorize('view-sales');
        
        $user = Auth::user();
        $query = Penjualan::with(['konsumen', 'sales', 'lokasi', 'details'])->latest();

        // [MODIFIKASI] Filter Lokasi sesuai Poin 17
        // User Global (SA/PIC) atau Pusat (ASD/ACC) bisa lihat semua data dealer
        $isGlobalOrPusat = $user->isGlobal() || ($user->isPusat() && $user->hasRole(['ASD', 'ACC']));

        if (!$isGlobalOrPusat) {
            // User Dealer (KC, PC, KSR) hanya lihat datanya sendiri
            if ($user->lokasi_id) {
                $query->where('lokasi_id', $user->lokasi_id);
            } else {
                // Safety: Jika user tidak punya lokasi dan bukan admin, tidak bisa lihat apa-apa
                $query->whereRaw('1 = 0');
            }
        }

        $penjualans = $query->paginate(15);
        return view('admin.penjualans.index', compact('penjualans'));
    }

    public function create()
    {
        // [MODIFIKASI] Gate Poin 17: create-sale (Hanya PC & KSR)
        $this->authorize('create-sale');
        
        $user = Auth::user();

        // Validasi Lokasi User
        if (!$user->lokasi_id && !$user->isGlobal()) {
             return redirect()->route('admin.home')
                ->with('error', 'Akun Anda tidak terasosiasi dengan lokasi/cabang manapun. Hubungi Admin.');
        }

        // Ambil lokasi aktif (Dealer user)
        $lokasi = $user->lokasi;
        
        // Fallback untuk SA saat testing (ambil sembarang dealer)
        if (!$lokasi && $user->isGlobal()) {
            $lokasi = Lokasi::where('tipe', 'DEALER')->first();
        }

        $today = now()->format('Y-m-d');

        return view('admin.penjualans.create', compact('lokasi', 'today'));
    }

    public function store(Request $request)
    {
        // [MODIFIKASI] Gate create-sale
        $this->authorize('create-sale');
        
        $user = Auth::user();

        // 1. Validasi Input Dasar
        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:255',
            'tipe_konsumen' => 'required|in:BENGKEL,RETAIL',
            'alamat'        => 'nullable|string|max:500',
            'telepon'       => 'nullable|string|max:20',
            'tanggal_jual'  => 'required|date',
            'items'         => 'required|array|min:1',
            'items.*.barang_id' => 'required|exists:barangs,id',
            'items.*.qty'   => 'required|integer|min:1',
            'nilai_diskon'  => 'nullable|numeric|min:0',
            'ppn_check'     => 'nullable|in:1,0',
        ], [
            'items.required' => 'Keranjang belanja masih kosong.',
            'items.min' => 'Minimal masukan 1 barang.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // [MODIFIKASI] Lock Lokasi ke User
        $lokasiId = $user->lokasi_id;
        
        if (!$lokasiId && $user->isGlobal()) {
             $lokasiId = Lokasi::where('tipe', 'DEALER')->first()->id ?? null;
        }

        if (!$lokasiId) {
            return back()->with('error', 'Lokasi penjualan tidak valid.')->withInput();
        }

        DB::beginTransaction();
        try {
            // 2. Handle Data Konsumen (Cari atau Buat Baru)
            $konsumen = Konsumen::firstOrCreate(
                ['nama_konsumen' => $request->customer_name],
                [
                    'kode_konsumen' => 'CST-' . now()->format('ymd-His'),
                    'tipe_konsumen' => $request->tipe_konsumen,
                    'alamat'        => $request->alamat ?? '-',
                    'telepon'       => $request->telepon ?? '-',
                    'is_active'     => true
                ]
            );

            // Jika konsumen sudah ada, update info kontaknya jika diisi
            if (!$konsumen->wasRecentlyCreated) {
                $konsumen->update([
                    'tipe_konsumen' => $request->tipe_konsumen,
                    'alamat' => $request->alamat ?? $konsumen->alamat,
                    'telepon' => $request->telepon ?? $konsumen->telepon,
                ]);
            }

            // 3. Buat Header Penjualan (Draft Awal)
            $penjualan = Penjualan::create([
                'nomor_faktur' => Penjualan::generateNomorFaktur(),
                'tanggal_jual' => $request->tanggal_jual,
                'lokasi_id'    => $lokasiId,
                'konsumen_id'  => $konsumen->id,
                'sales_id'     => $user->id,
                'created_by'   => $user->id,
                'status'       => 'COMPLETED',
                'keterangan_diskon' => $request->nama_diskon,
                'diskon'       => 0, // Dihitung ulang nanti
                'subtotal'     => 0, // Dihitung ulang nanti
                'pajak'        => 0, // Dihitung ulang nanti
                'total_harga'  => 0, // Dihitung ulang nanti
            ]);

            $subtotalGlobal = 0;

            // 4. Proses Setiap Item (CORE LOGIC)
            foreach ($request->items as $item) {
                $barangId = $item['barang_id'];
                $qtyRequest = (int) $item['qty'];
                
                // Ambil data barang untuk harga saat ini
                $barang = Barang::find($barangId);
                $hargaJualSatuan = $barang->retail; // Harga master

                // Ambil stok dari inventory_batch (FIFO)
                $batches = InventoryBatch::where('barang_id', $barangId)
                    ->where('lokasi_id', $lokasiId)
                    ->where('quantity', '>', 0)
                    ->orderBy('created_at', 'asc')
                    ->lockForUpdate() // [PENTING] Lock baris agar tidak race condition
                    ->get();

                $totalStokTersedia = $batches->sum('quantity');

                if ($totalStokTersedia < $qtyRequest) {
                    throw new \Exception("Stok tidak mencukupi untuk barang: {$barang->part_name}. Diminta: {$qtyRequest}, Tersedia: {$totalStokTersedia}");
                }

                $sisaQtyYangHarusDipenuhi = $qtyRequest;

                // Loop batches untuk mengurangi stok (Split Rak/Batch)
                foreach ($batches as $batch) {
                    if ($sisaQtyYangHarusDipenuhi <= 0) break;

                    // Ambil sebanyak mungkin dari batch ini
                    $qtyDiambil = min($batch->quantity, $sisaQtyYangHarusDipenuhi);

                    // 4a. Buat Penjualan Detail
                    $subtotalItem = $qtyDiambil * $hargaJualSatuan;
                    
                    $penjualan->details()->create([
                        'barang_id'   => $barang->id,
                        'rak_id'      => $batch->rak_id, // Penting untuk pelacakan
                        'qty_jual'    => $qtyDiambil,
                        'harga_jual'  => $hargaJualSatuan,
                        'subtotal'    => $subtotalItem,
                        'qty_diretur' => 0
                    ]);

                    // 4b. Update Inventory Batch (Kurangi Stok)
                    $stokAwalBatch = $batch->quantity;
                    $batch->decrement('quantity', $qtyDiambil);

                    // 4c. Catat Kartu Stok (Movement)
                    StockMovement::create([
                        'barang_id'      => $barang->id,
                        'lokasi_id'      => $lokasiId,
                        'rak_id'         => $batch->rak_id,
                        'jumlah'         => -$qtyDiambil, // Keluar = Negatif
                        'stok_sebelum'   => $stokAwalBatch,
                        'stok_sesudah'   => $stokAwalBatch - $qtyDiambil,
                        'referensi_type' => get_class($penjualan),
                        'referensi_id'   => $penjualan->id,
                        'keterangan'     => "Penjualan POS #{$penjualan->nomor_faktur}",
                        'user_id'        => $user->id,
                    ]);

                    $sisaQtyYangHarusDipenuhi -= $qtyDiambil;
                    $subtotalGlobal += $subtotalItem;
                }
            }

            // 5. Kalkulasi Final (Diskon & Pajak)
            $inputDiskon = (float) $request->nilai_diskon;
            // Pastikan diskon tidak melebihi subtotal
            $finalDiskon = min($inputDiskon, $subtotalGlobal);
            
            $dpp = $subtotalGlobal - $finalDiskon;
            
            $nilaiPajak = 0;
            if ($request->has('ppn_check') && $request->ppn_check == 1) {
                $nilaiPajak = $dpp * 0.11; // PPN 11%
            }

            $grandTotal = $dpp + $nilaiPajak;

            // 6. Update Header Penjualan
            $penjualan->update([
                'subtotal'     => $subtotalGlobal,
                'diskon'       => $finalDiskon,
                'total_diskon' => $finalDiskon,
                'pajak'        => $nilaiPajak,
                'total_harga'  => $grandTotal
            ]);

            DB::commit();

            return redirect()->route('admin.penjualans.show', $penjualan->id)
                ->with('success', 'Transaksi Penjualan Berhasil Disimpan.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal memproses transaksi: ' . $e->getMessage())->withInput();
        }
    }

    public function show(Penjualan $penjualan)
    {
        // [MODIFIKASI] Gate view-sales
        $this->authorize('view-sales');
        
        $user = Auth::user();
        
        // [MODIFIKASI] Security Check: Dealer hanya boleh lihat penjualan di lokasinya
        $isGlobalOrPusat = $user->isGlobal() || ($user->isPusat() && $user->hasRole(['ASD', 'ACC']));
        
        if (!$isGlobalOrPusat) {
            if ($user->lokasi_id && $penjualan->lokasi_id != $user->lokasi_id) {
                abort(403, 'Akses Ditolak: Ini bukan data lokasi Anda.');
            }
        }

        $penjualan->load(['konsumen', 'lokasi', 'sales', 'details.barang', 'details.rak']);
        return view('admin.penjualans.show', compact('penjualan'));
    }

    public function print(Penjualan $penjualan)
    {
        $this->authorize('view-sales');
        
        // (Optional) Security Check same as show method here
        
        return view('admin.penjualans.print', compact('penjualan'));
    }

    // API untuk Select2
    public function getBarangItems(Request $request)
    {
        $user = Auth::user();
        $search = $request->q;
        $lokasiId = $request->lokasi_id;

        if (!$lokasiId) return response()->json([]);

        $barangs = Barang::where(function($q) use ($search) {
                $q->where('part_name', 'LIKE', "%$search%")
                  ->orWhere('part_code', 'LIKE', "%$search%");
            })
            ->where('is_active', true)
            ->get();

        $results = [];
        foreach ($barangs as $barang) {
            // Hitung total stok real-time di lokasi tersebut
            $stok = InventoryBatch::where('barang_id', $barang->id)
                ->where('lokasi_id', $lokasiId)
                ->sum('quantity');

            if ($stok > 0) {
                $results[] = [
                    'id' => $barang->id,
                    'text' => $barang->part_name . ' (' . $barang->part_code . ') - Stok: ' . $stok,
                    'price' => $barang->retail,
                    'stock' => $stok // Kirim stok ke frontend untuk validasi JS
                ];
            }
        }

        return response()->json($results);
    }
    
    // API Hitung Diskon (Opsional)
    public function calculateDiscount(Request $request)
    {
        return response()->json(['discount' => 0]);
    }
}