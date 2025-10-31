<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Penjualan;
use App\Models\Konsumen;
use App\Models\Lokasi;
use App\Models\User;
use App\Models\Convert; // Model yang kita gunakan
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

    /**
     * Menyimpan penjualan baru berdasarkan item dari tabel 'converts'.
     */
    public function store(Request $request)
    {
        $this->authorize('create-sale');

        $validated = $request->validate([
            'lokasi_id' => 'required|exists:lokasi,id',
            'konsumen_id' => 'required|exists:konsumens,id',
            'tanggal_jual' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.convert_id' => 'required|exists:converts,id',
        ], [
            'items.required' => 'Setidaknya satu item harus ditambahkan ke penjualan.',
            'items.*.convert_id.required' => 'Item/Jasa harus dipilih pada semua baris.',
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

            $aggregatedItems = [];
            foreach ($validated['items'] as $item) {
                $convertId = $item['convert_id'];
                if (isset($aggregatedItems[$convertId])) {
                     $aggregatedItems[$convertId]['count'] += 1;
                } else {
                    $convertObject = Convert::find($convertId);
                    if (!$convertObject) {
                         throw new \Exception("Item dengan ID {$convertId} tidak ditemukan.");
                    }
                    $aggregatedItems[$convertId] = [
                        'convert_id' => $convertId,
                        'count' => 1,
                        'convert_object' => $convertObject
                    ];
                }
            }

            foreach ($aggregatedItems as $convertId => $item) {
                $convert = $item['convert_object'];

                $qtyPerItem = $convert->quantity;
                $hargaPerItem = $convert->harga_jual;
                $itemSubtotal = $hargaPerItem * $qtyPerItem;

                $totalCount = $item['count'];
                $totalQty = $qtyPerItem * $totalCount;
                $totalSubtotal = $itemSubtotal * $totalCount;

                $totalSubtotalServer += $totalSubtotal;

                $penjualan->details()->create([
                    'convert_id' => $convert->id,
                    'part_id' => null,
                    'rak_id' => null,
                    'qty_jual' => $totalQty,
                    'harga_jual' => $hargaPerItem,
                    'subtotal' => $totalSubtotal,
                ]);
            }

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
            Log::error("Store Penjualan (Convert) Gagal: " . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage())->withInput();
        }
    }

    public function show(Penjualan $penjualan)
    {
        $this->authorize('view-sales');
        $penjualan->load(['konsumen', 'lokasi', 'sales', 'details.convert']);

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
            'details.convert',
            'details.part',
            'details.rak'
        ]);
        return view('admin.penjualans.print', compact('penjualan'));
    }

    // --- API Methods ---

    /**
     * API BARU: Mengambil item dari tabel CONVERTS
     */
    public function getConvertItems(Request $request)
    {
        $this->authorize('create-sale');

        // ++ PERBAIKAN: Hapus 'where('is_active', true)' ++
        $items = Convert::orderBy('nama_job')
                        ->get([
                            'id',
                            // 'nama_job',
                            'part_name',
                            'quantity',
                            'harga_jual'
                        ]);

        // Ubah format untuk Select2
        $formattedItems = $items->map(function($item) {
             return [
                 'id' => $item->id,
                 'text' => $item->part_name,
                 'data' => $item // Kirim data lengkap
             ];
         });

        return response()->json($formattedItems);
    }
}
