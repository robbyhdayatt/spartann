<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Models\Lokasi;
use App\Models\Barang;
use App\Models\Supplier;
use App\Models\InventoryBatch;
use App\Models\StockMovement;
use App\Services\PurchaseOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDF;

class PurchaseOrderController extends Controller
{
    protected $poService;

    public function __construct(PurchaseOrderService $poService)
    {
        $this->poService = $poService;
    }

    public function index(Request $request)
    {
        $this->authorize('view-po'); 
        $defaultType = 'dealer_request';
        
        if (Auth::user()->isGudang()) {
            $defaultType = 'supplier_po';
        }

        $type = $request->get('type', $defaultType);
        $query = PurchaseOrder::with(['lokasi', 'supplier', 'createdBy', 'sumberLokasi'])
            ->where('po_type', $type)
            ->latest();

        if (Auth::user()->isDealer()) {
            $query->where('lokasi_id', Auth::user()->lokasi_id);
        }

        $purchaseOrders = $query->get();

        return view('admin.purchase_orders.index', compact('purchaseOrders', 'type'));
    }

    public function create()
    {
        $this->authorize('create-po');
        $user = Auth::user();
        $barangs = Barang::where('is_active', true)->orderBy('part_name')->get();

        if ($user->can('create-po-dealer') || ($user->isDealer() && $user->hasRole('PC'))) {
            $sumberPusat = Lokasi::where('tipe', 'GUDANG')->first();
            if(!$sumberPusat) $sumberPusat = Lokasi::where('tipe', 'PUSAT')->first();

            if ($user->isGlobal() || $user->isPusat()) {
                $dealers = Lokasi::where('tipe', 'DEALER')->where('is_active', true)->orderBy('nama_lokasi')->get();
            } else {
                $dealers = Lokasi::where('id', $user->lokasi_id)->get();
            }

            return view('admin.purchase_orders.create_request', compact('sumberPusat', 'dealers', 'barangs'));
        }

        if ($user->can('create-po-supplier')) {
            $suppliers = Supplier::where('is_active', true)->get();
            return view('admin.purchase_orders.create_supplier', compact('suppliers', 'barangs'));
        }

        abort(403, 'Akses Ditolak.');
    }

    public function store(Request $request)
    {
        $this->authorize('create-po');

        $request->validate([
            'tanggal_po' => 'required|date',
            'po_type'    => 'required|in:dealer_request,supplier_po',
        ]);

        if ($request->po_type === 'supplier_po') {
            if (Auth::user()->cannot('create-po-supplier')) abort(403);
            return $this->storeSupplierPO($request);
        } else {
            return $this->storeDealerRequest($request);
        }
    }

    protected function storeSupplierPO(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'items'       => 'required|array',
            'items.*.barang_id' => 'required|exists:barangs,id',
            'items.*.qty'       => 'required|integer|min:1',
        ]);

        try {
            $this->poService->createSupplierPO($request->all(), Auth::user());

            return redirect()->route('admin.purchase-orders.index', ['type' => 'supplier_po'])
                             ->with('success', 'PO ke Supplier berhasil dibuat.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error: ' . $e->getMessage())->withInput();
        }
    }

    protected function storeDealerRequest(Request $request)
    {
        $request->validate([
            'sumber_lokasi_id' => 'required|exists:lokasi,id',
            'requests' => 'required|string',
        ]);

        try {
            $count = $this->poService->createDealerRequest($request->all(), Auth::user());

            return redirect()->route('admin.purchase-orders.index', ['type' => 'dealer_request'])
                ->with('success', "Berhasil membuat $count Request.");
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal: ' . $e->getMessage())->withInput();
        }
    }

    public function show($id)
    {
        $this->authorize('view-po');
        
        $purchaseOrder = PurchaseOrder::with(['lokasi', 'sumberLokasi', 'details.barang', 'createdBy', 'approvedBy'])
            ->findOrFail($id);
            
        if ($purchaseOrder->po_type === 'dealer_request') {
             foreach ($purchaseOrder->details as $detail) {
                 $detail->stok_sumber = InventoryBatch::where('lokasi_id', $purchaseOrder->sumber_lokasi_id)
                      ->where('barang_id', $detail->barang_id)
                      ->sum('quantity');
             }
        }

        return view('admin.purchase_orders.show', compact('purchaseOrder'));
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorize('approve-po', $purchaseOrder);
        
        try {
            $this->poService->approvePO($purchaseOrder, $request->qty_approved ?? [], Auth::user());

            return back()->with('success', 'PO Disetujui.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorize('approve-po', $purchaseOrder);
        $request->validate(['rejection_reason' => 'required']);

        $purchaseOrder->update([
            'status' => 'REJECTED',
            'rejection_reason' => $request->rejection_reason,
            'approved_by' => Auth::id(),
            'approved_at' => now()
        ]);

        return back()->with('success', 'PO Ditolak.');
    }
    
    public function pdf(PurchaseOrder $purchaseOrder)
    {
         $pdf = PDF::loadView('admin.purchase_orders.print', compact('purchaseOrder'));
         return $pdf->stream('PO.pdf');
    }
}