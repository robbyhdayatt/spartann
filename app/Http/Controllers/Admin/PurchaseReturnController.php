<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseReturn;
use App\Models\Receiving;
use App\Models\ReceivingDetail;
use App\Models\InventoryBatch;
use App\Models\Rak;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PDF;

class PurchaseReturnController extends Controller
{
    public function index()
    {
        $this->authorize('manage-purchase-returns');
        $returns = PurchaseReturn::with(['supplier', 'receiving', 'createdBy'])->latest()->get();
        return view('admin.purchase_returns.index', compact('returns'));
    }

    public function create()
    {
        $this->authorize('manage-purchase-returns');
        
        // FIX POIN 1: Ubah 'supplier' jadi 'purchaseOrder.supplier'
        // Ambil penerimaan yang memiliki item gagal QC dan belum diretur sepenuhnya
        $receivings = Receiving::with(['purchaseOrder.supplier'])
            ->whereHas('details', function ($query) {
                $query->whereRaw('qty_gagal_qc > qty_diretur');
            })
            ->latest()
            ->get();

        return view('admin.purchase_returns.create', compact('receivings'));
    }

    // API Helper untuk mengambil item gagal QC via AJAX
    public function getFailedItems(Receiving $receiving)
    {
        $items = $receiving->details()
            ->with('barang')
            ->whereRaw('qty_gagal_qc > qty_diretur')
            ->get()
            ->map(function ($detail) {
                // Tambahkan sisa yang bisa diretur ke objek JSON
                $detail->sisa_retur = $detail->qty_gagal_qc - $detail->qty_diretur;
                return $detail;
            });

        return response()->json($items);
    }

    public function store(Request $request)
    {
        $this->authorize('manage-purchase-returns');
        
        // 1. Validasi Input
        $validator = Validator::make($request->all(), [
            'receiving_id' => 'required|exists:receivings,id',
            'tanggal_retur' => 'required|date|before_or_equal:today',
            'catatan' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.receiving_detail_id' => 'required|exists:receiving_details,id',
            'items.*.qty_retur' => 'required|integer|min:0',
            'items.*.alasan' => 'nullable|string|max:150',
        ], [
            'receiving_id.required' => 'Dokumen Penerimaan wajib dipilih.',
            'items.required' => 'Minimal satu barang harus diretur.',
            'items.*.qty_retur.min' => 'Jumlah retur tidak boleh negatif.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            // Cek apakah ada item yang qty > 0
            $hasQty = collect($request->items)->sum('qty_retur') > 0;
            if (!$hasQty) {
                throw new \Exception("Mohon isi jumlah retur minimal pada satu barang.");
            }

            // 2. Lock Receiving Document
            $receiving = Receiving::with('purchaseOrder', 'lokasi')
                ->lockForUpdate() // PESSIMISTIC LOCKING
                ->findOrFail($request->receiving_id);

            $lokasiId = $receiving->lokasi_id;

            // 3. Cari Rak Karantina di Lokasi Tersebut
            $rakKarantina = Rak::where('lokasi_id', $lokasiId)
                                ->where('tipe_rak', 'KARANTINA')
                                ->first();

            if (!$rakKarantina) {
                throw new \Exception("Rak Karantina tidak ditemukan di lokasi penerimaan ({$receiving->lokasi->nama_lokasi}). Sistem tidak dapat memotong stok gagal QC.");
            }

            // 4. Buat Header Retur
            $purchaseReturn = PurchaseReturn::create([
                'nomor_retur' => PurchaseReturn::generateReturnNumber(),
                'receiving_id' => $receiving->id,
                'supplier_id' => $receiving->purchaseOrder->supplier_id ?? null,
                'tanggal_retur' => $request->tanggal_retur,
                'catatan' => $request->catatan,
                'created_by' => Auth::id(),
            ]);

            // 5. Loop Items
            foreach ($request->items as $itemData) {
                $qtyRetur = (int)$itemData['qty_retur'];
                
                if ($qtyRetur <= 0) continue;

                // Lock Detail Penerimaan
                $detail = ReceivingDetail::with('barang')
                    ->where('id', $itemData['receiving_detail_id'])
                    ->where('receiving_id', $receiving->id) // Pastikan detail milik receiving yang benar
                    ->lockForUpdate() // PESSIMISTIC LOCKING
                    ->firstOrFail();

                // Validasi ulang stok vs request
                $maxRetur = $detail->qty_gagal_qc - $detail->qty_diretur;
                if ($qtyRetur > $maxRetur) {
                    throw new \Exception("Gagal! Barang {$detail->barang->part_name} hanya memiliki sisa {$maxRetur} unit untuk diretur (Diminta: {$qtyRetur}). Data mungkin telah berubah.");
                }

                // 6. Kurangi Stok Fisik dari Batch (FIFO) di Rak Karantina
                $this->processQuarantineStockDeduction(
                    $detail->barang_id,
                    $rakKarantina->id,
                    $lokasiId,
                    $qtyRetur,
                    $purchaseReturn,
                    $itemData['alasan'] ?? null
                );

                // 7. Update Receiving Detail
                $detail->increment('qty_diretur', $qtyRetur);

                // 8. Buat Detail Retur
                $purchaseReturn->details()->create([
                    'barang_id' => $detail->barang_id,
                    'receiving_detail_id' => $detail->id,
                    'qty_retur' => $qtyRetur,
                    'alasan' => $itemData['alasan'] ?? null,
                ]);
            }

            DB::commit();
            return redirect()->route('admin.purchase-returns.index')
                ->with('success', 'Retur Pembelian berhasil disimpan: ' . $purchaseReturn->nomor_retur);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi Kesalahan: ' . $e->getMessage())->withInput();
        }
    }

    public function show(PurchaseReturn $purchaseReturn)
    {
        $this->authorize('manage-purchase-returns');
        $purchaseReturn->load(['supplier', 'receiving.purchaseOrder', 'details.barang', 'createdBy']);
        return view('admin.purchase_returns.show', compact('purchaseReturn'));
    }

    private function processQuarantineStockDeduction($barangId, $rakId, $lokasiId, $qtyNeeded, $docRef, $alasan)
    {
        $remaining = $qtyNeeded;

        // Ambil batch stok karantina, urutkan dari yang terlama (FIFO)
        $batches = InventoryBatch::where('barang_id', $barangId)
            ->where('rak_id', $rakId)
            ->where('lokasi_id', $lokasiId)
            ->where('quantity', '>', 0)
            ->orderBy('created_at', 'asc')
            ->lockForUpdate()
            ->get();

        $totalAvailable = $batches->sum('quantity');

        if ($totalAvailable < $qtyNeeded) {
            $namaBarang = \App\Models\Barang::find($barangId)->part_name ?? 'Unknown';
            throw new \Exception("Stok karantina fisik tidak mencukupi untuk {$namaBarang}. Tersedia: {$totalAvailable}, Dibutuhkan: {$qtyNeeded}. Pastikan barang sudah dipindah ke rak karantina saat penerimaan.");
        }

        foreach ($batches as $batch) {
            if ($remaining <= 0) break;

            $take = min($batch->quantity, $remaining);
            
            $batch->decrement('quantity', $take);
            
            // Catat Kartu Stok
            StockMovement::create([
                'barang_id' => $barangId,
                'lokasi_id' => $lokasiId,
                'rak_id' => $rakId,
                'jumlah' => -$take, // Negatif karena keluar (retur)
                'stok_sebelum' => $batch->quantity + $take,
                'stok_sesudah' => $batch->quantity,
                'referensi_type' => get_class($docRef),
                'referensi_id' => $docRef->id,
                'keterangan' => 'Retur ke Supplier: ' . ($alasan ?: '-'),
                'user_id' => Auth::id(),
            ]);

            $remaining -= $take;
        }
    }

    public function pdf(PurchaseReturn $purchaseReturn)
    {
        $this->authorize('manage-purchase-returns');
        
        $purchaseReturn->load(['supplier', 'receiving.lokasi', 'details.barang', 'createdBy']);

        $data = ['purchaseReturn' => $purchaseReturn];

        // === KONFIGURASI KERTAS (Disamakan dengan Faktur/PO) ===
        // Ukuran: 24cm x 14cm (Landscape Faktur)
        $width_cm = 24;
        $height_cm = 14;
        $points_per_cm = 28.3465;
        $widthInPoints = $width_cm * $points_per_cm; // ~680.3
        $heightInPoints = $height_cm * $points_per_cm; // ~396.8
        
        $customPaper = [0, 0, $widthInPoints, $heightInPoints];

        // Load View
        $pdf = PDF::loadView('admin.purchase_returns.print', $data);

        // Set Paper
        $pdf->setPaper($customPaper);

        // Set Options (Sama persis dengan PdfController)
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'dpi' => 150,
            'defaultFont' => 'Arial',
            'margin-top'    => 0,
            'margin-right'  => 0,
            'margin-bottom' => 0,
            'margin-left'   => 0,
            'enable-smart-shrinking' => true,
            'disable-smart-shrinking' => false,
            'lowquality' => false
        ]);

        return $pdf->download('Retur-' . $purchaseReturn->nomor_retur . '.pdf');
    }
}