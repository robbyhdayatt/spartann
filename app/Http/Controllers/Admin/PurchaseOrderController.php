<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Lokasi; // Menggunakan model Lokasi
use App\Models\Part;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\DiscountService;

class PurchaseOrderController extends Controller
{
    protected $discountService;

    public function __construct(DiscountService $discountService)
    {
        $this->discountService = $discountService;
    }

    public function index()
    {
        // PERUBAHAN: Menggunakan relasi 'lokasi' yang baru
        $purchaseOrders = PurchaseOrder::with(['supplier', 'lokasi', 'createdBy'])->latest()->get();
        return view('admin.purchase_orders.index', compact('purchaseOrders'));
    }

    public function create()
    {
        $this->authorize('create-po'); // Gate ini sudah benar (hanya untuk Admin Gudang)

        $suppliers = Supplier::where('is_active', true)->orderBy('nama_supplier')->get();
        $parts = Part::where('is_active', true)->orderBy('nama_part')->get();

        // PERUBAHAN BESAR: Logika disederhanakan, PO hanya untuk Gudang Pusat.
        $gudangPusat = Lokasi::where('tipe', 'PUSAT')->firstOrFail();

        return view('admin.purchase_orders.create', compact('suppliers', 'gudangPusat', 'parts'));
    }

    public function store(Request $request)
    {
        $this->authorize('create-po');
        $validated = $request->validate([
            'tanggal_po' => 'required|date',
            'supplier_id' => 'required|exists:suppliers,id',
            'gudang_id' => 'required|exists:lokasi,id', // PERUBAHAN: Validasi ke tabel 'lokasi'
            'catatan' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.part_id' => 'required|exists:parts,id',
            'items.*.qty' => 'required|integer|min:1',
            'use_ppn' => 'nullable|boolean',
        ]);

        // PERUBAHAN: Validasi tambahan untuk memastikan gudang_id adalah Gudang Pusat
        $lokasi = Lokasi::find($validated['gudang_id']);
        if (!$lokasi || $lokasi->tipe !== 'PUSAT') {
            return back()->with('error', 'Purchase Order hanya dapat dibuat untuk Gudang Pusat.')->withInput();
        }

        DB::beginTransaction();
        try {
            $supplier = Supplier::find($validated['supplier_id']);
            $totalSubtotal = 0;
            $itemsToSave = [];

            foreach ($validated['items'] as $itemData) {
                $part = Part::find($itemData['part_id']);
                $qty = (int)$itemData['qty'];
                $discountResult = $this->discountService->calculatePurchaseDiscount($part, $supplier, $part->harga_beli_default);
                $finalPrice = $discountResult['final_price'];
                $itemSubtotal = $qty * $finalPrice;
                $totalSubtotal += $itemSubtotal;

                $itemsToSave[] = [
                    'part_id' => $part->id,
                    'qty_pesan' => $qty,
                    'harga_beli' => $finalPrice,
                    'subtotal' => $itemSubtotal,
                ];
            }

            $pajak = ($request->has('use_ppn') && $request->use_ppn) ? $totalSubtotal * 0.11 : 0;
            $totalAmount = $totalSubtotal + $pajak;

            $po = PurchaseOrder::create([
                'nomor_po' => $this->generatePoNumber(),
                'tanggal_po' => $validated['tanggal_po'],
                'supplier_id' => $validated['supplier_id'],
                'gudang_id' => $validated['gudang_id'], // Ini sudah pasti ID Gudang Pusat
                'catatan' => $request->catatan,
                'status' => 'PENDING_APPROVAL',
                'created_by' => Auth::id(),
                'subtotal' => $totalSubtotal,
                'pajak' => $pajak,
                'total_amount' => $totalAmount,
            ]);

            $po->details()->createMany($itemsToSave);

            DB::commit();
            return redirect()->route('admin.purchase-orders.index')->with('success', 'Purchase Order berhasil dibuat.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage())->withInput();
        }
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        // PERUBAHAN: Menggunakan relasi 'lokasi'
        $purchaseOrder->load(['supplier', 'lokasi', 'details.part']);
        $creatorName = User::find($purchaseOrder->created_by)->nama ?? 'N/A';
        return view('admin.purchase_orders.show', compact('purchaseOrder', 'creatorName'));
    }

    // Fungsi approve, reject, generatePoNumber, getPartPurchaseDetails, getPoDetailsApi tidak perlu diubah
    // ... (salin sisa fungsi dari file lama Anda ke sini) ...
    public function approve(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('approve-po', $purchaseOrder);
        if ($purchaseOrder->status !== 'PENDING_APPROVAL') {
            return back()->with('error', 'Hanya PO yang berstatus PENDING APPROVAL yang bisa disetujui.');
        }
        $purchaseOrder->update([
            'status' => 'APPROVED',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);
        return redirect()->route('admin.purchase-orders.show', $purchaseOrder)->with('success', 'Purchase Order berhasil disetujui.');
    }

    public function reject(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorize('approve-po', $purchaseOrder);
        $request->validate(['rejection_reason' => 'required|string|min:10']);
        if ($purchaseOrder->status !== 'PENDING_APPROVAL') {
            return back()->with('error', 'Hanya PO yang berstatus PENDING APPROVAL yang bisa ditolak.');
        }
        $purchaseOrder->update([
            'status' => 'REJECTED',
            'rejection_reason' => $request->rejection_reason,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);
        return redirect()->route('admin.purchase-orders.show', $purchaseOrder)->with('success', 'Purchase Order berhasil ditolak.');
    }

    private function generatePoNumber()
    {
        $date = now()->format('Ymd');
        $latestPo = PurchaseOrder::whereDate('created_at', today())->count();
        $sequence = str_pad($latestPo + 1, 4, '0', STR_PAD_LEFT);
        return "PO/{$date}/{$sequence}";
    }

    public function getPartPurchaseDetails(Part $part, Request $request)
    {
        $validated = $request->validate(['supplier_id' => 'required|exists:suppliers,id']);
        $supplier = Supplier::find($validated['supplier_id']);
        $discountResult = $this->discountService->calculatePurchaseDiscount($part, $supplier, $part->harga_beli_default);
        return response()->json(['discount_result' => $discountResult]);
    }

    public function getPoDetailsApi(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load('details.part');
        $details = $purchaseOrder->details->map(function ($detail) {
            return [
                'po_detail_id' => $detail->id,
                'part_id' => $detail->part->id,
                'kode_part' => $detail->part->kode_part,
                'nama_part' => $detail->part->nama_part,
                'qty_pesan' => (int) $detail->qty_pesan,
                'qty_sudah_diterima' => (int) $detail->qty_diterima,
                'qty_sisa' => (int) ($detail->qty_pesan - $detail->qty_diterima),
            ];
        });
        return response()->json($details);
    }
}
