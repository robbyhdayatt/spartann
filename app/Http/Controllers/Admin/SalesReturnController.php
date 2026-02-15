<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SalesReturn;
use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Models\InventoryBatch;
use App\Models\Lokasi;
use App\Models\Rak;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SalesReturnController extends Controller
{
    public function index()
    {
        $this->authorize('view-sales');

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $query = SalesReturn::with(['konsumen', 'penjualan', 'lokasi'])->latest();

        if (!$user->hasRole(['SA', 'PIC', 'MA'])) {
            $query->where('lokasi_id', $user->lokasi_id);
        }

        $returns = $query->get();
        return view('admin.sales_returns.index', compact('returns'));
    }

    public function create()
    {
        $this->authorize('create-sale');

        /** @var \App\Models\User $user */
        $user = Auth::user();
        
        $query = Penjualan::whereHas('details', function ($q) {
            $q->where(DB::raw('qty_jual - qty_diretur'), '>', 0);
        });

        if (!$user->hasRole(['SA', 'PIC', 'MA'])) {
            $query->where('lokasi_id', $user->lokasi_id);
        }

        $penjualans = $query->latest()->get();
        return view('admin.sales_returns.create', compact('penjualans'));
    }

    public function store(Request $request)
    {
        $this->authorize('create-sale');

        $validator = Validator::make($request->all(), [
            'penjualan_id' => 'required|exists:penjualans,id',
            'tanggal_retur' => 'required|date|before_or_equal:today',
            'items' => 'required|array|min:1',
            'items.*.qty_retur' => 'required|integer|min:0',
        ], [
            'penjualan_id.required' => 'Faktur penjualan wajib dipilih.',
            'tanggal_retur.required' => 'Tanggal retur wajib diisi.',
            'tanggal_retur.before_or_equal' => 'Tanggal retur tidak boleh melebihi hari ini.',
            'items.required' => 'Minimal satu item harus dipilih untuk diretur.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            // 1. Lock data Penjualan utama
            $penjualan = Penjualan::where('id', $request->penjualan_id)->lockForUpdate()->firstOrFail();

            // Cek apakah user mengirim semua qty 0
            $hasQty = false;
            foreach ($request->items as $item) {
                if (($item['qty_retur'] ?? 0) > 0) $hasQty = true;
            }

            if (!$hasQty) {
                throw new \Exception("Minimal masukkan jumlah retur 1 pada salah satu item.");
            }

            // 2. Cari/Buat Rak Karantina
            $rakKarantina = Rak::where('lokasi_id', $penjualan->lokasi_id)
                                ->where('tipe_rak', 'KARANTINA')
                                ->first();
            
            if (!$rakKarantina) {
                $rakKarantina = Rak::create([
                    'lokasi_id' => $penjualan->lokasi_id,
                    'tipe_rak' => 'KARANTINA',
                    'nama_rak' => 'RAK KARANTINA RETUR',
                    'kode_rak' => $penjualan->lokasi->kode_lokasi . '-KRN-RT'
                ]);
            }

            // 3. Simpan Header Sales Return
            $salesReturn = SalesReturn::create([
                'nomor_retur_jual' => SalesReturn::generateReturnNumber(),
                'penjualan_id' => $penjualan->id,
                'konsumen_id' => $penjualan->konsumen_id,
                'lokasi_id' => $penjualan->lokasi_id,
                'tanggal_retur' => $request->tanggal_retur,
                'catatan' => $request->catatan,
                'created_by' => auth()->id(),
                'total_retur' => 0, 
            ]);

            $totalNilaiRetur = 0;

            foreach ($request->items as $penjualanDetailId => $itemData) {
                $qtyReturRequest = (int)($itemData['qty_retur'] ?? 0);
                if ($qtyReturRequest <= 0) continue;

                // --- PENCEGAHAN RACE CONDITION ---
                // lockForUpdate() akan memastikan baris ini tidak bisa dibaca/ubah oleh 
                // request lain sampai transaksi ini selesai (commit).
                $penjualanDetail = PenjualanDetail::where('id', $penjualanDetailId)
                                    ->with('barang')
                                    ->lockForUpdate()
                                    ->firstOrFail();

                $maxBisaDiretur = $penjualanDetail->qty_jual - $penjualanDetail->qty_diretur;

                if ($qtyReturRequest > $maxBisaDiretur) {
                    throw new \Exception("Item {$penjualanDetail->barang->part_name} gagal diretur. Qty yang diminta ({$qtyReturRequest}) melebihi sisa yang bisa diretur ({$maxBisaDiretur}). Mungkin data sudah diubah oleh pengguna lain.");
                }

                $itemSubtotal = $penjualanDetail->harga_jual * $qtyReturRequest;
                $totalNilaiRetur += $itemSubtotal;

                // Simpan Detail Retur
                $salesReturn->details()->create([
                    'barang_id' => $penjualanDetail->barang_id,
                    'qty_retur' => $qtyReturRequest,
                    'harga_saat_jual' => $penjualanDetail->harga_jual,
                    'subtotal' => $itemSubtotal,
                ]);

                // Update Progress Retur di Penjualan Detail
                $penjualanDetail->increment('qty_diretur', $qtyReturRequest);

                // Tambah Stok di Karantina
                InventoryBatch::create([
                    'barang_id' => $penjualanDetail->barang_id,
                    'rak_id' => $rakKarantina->id,
                    'lokasi_id' => $penjualan->lokasi_id,
                    'quantity' => $qtyReturRequest,
                    'receiving_detail_id' => null,
                ]);

                // Riwayat Stok
                StockMovement::create([
                    'barang_id' => $penjualanDetail->barang_id,
                    'lokasi_id' => $penjualan->lokasi_id,
                    'rak_id' => $rakKarantina->id,
                    'jumlah' => $qtyReturRequest,
                    'stok_sebelum' => 0,
                    'stok_sesudah' => $qtyReturRequest,
                    'referensi_type' => get_class($salesReturn),
                    'referensi_id' => $salesReturn->id,
                    'keterangan' => 'Retur Penjualan: ' . $salesReturn->nomor_retur_jual,
                    'user_id' => auth()->id(),
                ]);
            }

            // Final Update Total
            $salesReturn->update(['total_retur' => $totalNilaiRetur]);

            DB::commit();
            return redirect()->route('admin.sales-returns.index')->with('success', 'Retur ' . $salesReturn->nomor_retur_jual . ' berhasil diproses.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function show(SalesReturn $salesReturn)
    {
        $this->authorize('view-sales');
        $salesReturn->load(['konsumen', 'penjualan', 'details.barang', 'lokasi', 'createdBy']);
        return view('admin.sales_returns.show', compact('salesReturn'));
    }

    public function getReturnableItems(Penjualan $penjualan)
    {
        $penjualan->load('details.barang');
        $returnableItems = $penjualan->details->filter(function ($detail) {
            return $detail->qty_jual > $detail->qty_diretur;
        })->map(function ($detail) {
            $detail->max_returnable = $detail->qty_jual - $detail->qty_diretur;
            return $detail;
        })->values();

        return response()->json($returnableItems);
    }
}