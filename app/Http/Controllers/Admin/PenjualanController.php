<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Penjualan;
use App\Models\Konsumen;
use App\Models\Lokasi;
use App\Models\User;
use App\Models\Barang; // Import model Barang
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // Import Log

class PenjualanController extends Controller
{
    // construct DiscountService dihapus karena tidak dipakai lagi

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

        $userLokasi = null;
        $allLokasi = collect();

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
            // --- VALIDASI DIUBAH ---
            'items.*.barang_id' => 'required|exists:barangs,id',
            'items.*.qty' => 'required|integer|min:1',
        ], [
            'items.required' => 'Setidaknya satu item harus ditambahkan ke penjualan.',
            'items.*.barang_id.required' => 'Item/Barang harus dipilih pada semua baris.',
            'items.*.qty.required' => 'Kuantitas (Qty) harus diisi pada semua baris.',
            'items.*.qty.min' => 'Kuantitas (Qty) minimal 1.',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $lokasiId = $validated['lokasi_id'];

        if (!$user->hasRole(['SA', 'PIC']) && $user->lokasi_id != $lokasiId) {
            abort(403, 'Aksi tidak diizinkan. Anda hanya dapat membuat penjualan dari lokasi Anda.');
        }

        DB::beginTransaction();
        try {
            $totalSubtotalServer = 0;

            $penjualan = Penjualan::create([
                'nomor_faktur' => Penjualan::generateNomorFaktur(),
                'tanggal_jual' => $validated['tanggal_jual'],
                'lokasi_id' => $lokasiId,
                'konsumen_id' => $validated['konsumen_id'],
                'sales_id' => $user->id,
                'created_by' => $user->id,
            ]);

            // --- LOGIKA PENYIMPANAN DIUBAH (HAPUS AGREGASI) ---
            foreach ($validated['items'] as $item) {
                $barang = Barang::find($item['barang_id']);
                if (!$barang) {
                    throw new \Exception("Item dengan ID {$item['barang_id']} tidak ditemukan.");
                }

                $qty = $item['qty'];
                $hargaJual = $barang->harga_jual; // Ambil harga dari master barang
                $subtotal = $hargaJual * $qty;

                $totalSubtotalServer += $subtotal;

                $penjualan->details()->create([
                    'barang_id' => $barang->id,  // <-- BARU
                    'convert_id' => null,     // <-- LAMA (null)
                    'part_id' => null,        // <-- LAMA (null)
                    'rak_id' => null,         // <-- LAMA (null)
                    'qty_jual' => $qty,
                    'harga_jual' => $hargaJual,
                    'subtotal' => $subtotal,
                ]);
            }
            // --- AKHIR PERUBAHAN LOGIKA ---

            $penjualan->update([
                'subtotal' => $totalSubtotalServer,
                'total_diskon' => 0,
                'pajak' => 0,
                'total_harga' => $totalSubtotalServer,
            ]);

            DB::commit();
            return redirect()->route('admin.penjualans.show', $penjualan)->with('success', 'Transaksi penjualan berhasil disimpan.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Store Penjualan (Barang) Gagal: " . $e->getMessage()); // Pesan log diubah
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage())->withInput();
        }
    }

    public function show(Penjualan $penjualan)
    {
        $this->authorize('view-sales');
        // --- UBAH 'details.convert' menjadi 'details.barang' ---
        $penjualan->load(['konsumen', 'lokasi', 'sales', 'details.barang']);

        if ($penjualan->details->contains('part_id', '!=', null)) {
            $penjualan->load('details.part', 'details.rak');
        }

        return view('admin.penjualans.show', compact('penjualan'));
    }

    public function print(Penjualan $penjualan)
    {
        $this->authorize('view-sales');
        $penjualan->load([
            'konsumen',
            'lokasi',
            'sales',
            // --- UBAH 'details.convert' menjadi 'details.barang' ---
            'details.barang',
            'details.part',
            'details.rak'
        ]);
        return view('admin.penjualans.print', compact('penjualan'));
    }

    // --- API Methods ---

    /**
     * API BARU: Mengambil item dari tabel BARANGS
     */
    public function getBarangItems(Request $request)
    {
        $this->authorize('create-sale');

        // Mengambil dari tabel 'barangs'
        $items = Barang::orderBy('part_name')
                        ->get([
                            'id',
                            'part_name',
                            'part_code',
                            'merk',
                            'harga_jual' // Kita perlu harga jual
                        ]);

        // Ubah format untuk Select2
        $formattedItems = $items->map(function($item) {
            $text = "{$item->part_name} ({$item->part_code})";
            if ($item->merk) {
                $text .= " - {$item->merk}";
            }

            return [
                'id' => $item->id,
                'text' => $text,
                'data' => $item // Kirim data lengkap
            ];
        });

        return response()->json($formattedItems);
    }
}
