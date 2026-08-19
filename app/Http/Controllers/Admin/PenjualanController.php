<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Penjualan;
use App\Models\Konsumen;
use App\Models\Lokasi;
use App\Models\Barang;
use App\Models\InventoryBatch;
use App\Services\PenjualanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PenjualanController extends Controller
{
    protected $penjualanService;

    public function __construct(PenjualanService $penjualanService)
    {
        $this->penjualanService = $penjualanService;
    }

    public function index()
    {
        $this->authorize('view-penjualan');
        
        $user = Auth::user();
        $query = Penjualan::with(['konsumen', 'sales', 'lokasi', 'details'])->latest();

        $isGlobalOrPusat = $user->isGlobal() || ($user->isPusat() && $user->hasRole(['ASD', 'ACC']));

        if (!$isGlobalOrPusat) {
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
        $this->authorize('manage-penjualan');
        
        $user = Auth::user();

        if (!$user->lokasi_id && !$user->isGlobal()) {
             return redirect()->route('admin.home')
                ->with('error', 'Akun Anda tidak terasosiasi dengan lokasi/cabang manapun. Hubungi Admin.');
        }

        $lokasi = $user->lokasi;
        
        if (!$lokasi && $user->isGlobal()) {
            $lokasi = Lokasi::where('tipe', 'DEALER')->first();
        }

        $today = now()->format('Y-m-d');

        return view('admin.penjualans.create', compact('lokasi', 'today'));
    }

    public function store(Request $request)
    {
        $this->authorize('manage-penjualan');
        
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

        try {
            $penjualan = $this->penjualanService->createPenjualan($request->all(), $user);

            return redirect()->route('admin.penjualans.show', $penjualan->id)
                ->with('success', 'Transaksi Penjualan Berhasil Disimpan.');

        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memproses transaksi: ' . $e->getMessage())->withInput();
        }
    }

    public function show(Penjualan $penjualan)
    {
        $this->authorize('view-penjualan');        
        $user = Auth::user();   
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
        $this->authorize('view-penjualan');
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
            // Ambil semua batch stok yang tersedia di lokasi ini (FIFO)
            $batches = InventoryBatch::where('barang_id', $barang->id)
                ->where('lokasi_id', $lokasiId)
                ->where('quantity', '>', 0)
                ->orderBy('created_at', 'asc') // Sort FIFO
                ->with('rak') // Load relasi rak
                ->get();

            $stok = $batches->sum('quantity');

            if ($stok > 0) {
                // Ambil info rak dari batch tertua (FIFO) untuk panduan user mengambil barang
                $rakTertua = $batches->first()->rak;
                $namaRak = $rakTertua ? $rakTertua->kode_rak . ' (' . $rakTertua->nama_rak . ')' : 'Rak Tidak Diketahui';

                $results[] = [
                    'id' => $barang->id,
                    'text' => $barang->part_name . ' (' . $barang->part_code . ') - Stok: ' . $stok,
                    'price' => $barang->retail,
                    'stock' => $stok,
                    'rak' => $namaRak 
                ];
            }
        }

        return response()->json($results);
    }
}