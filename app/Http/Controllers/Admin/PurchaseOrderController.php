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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDF;

class PurchaseOrderController extends Controller
{
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

        $gudangId = Auth::user()->lokasi_id; 

        DB::beginTransaction();
        try {
            $poNumber = 'PO-SUP-' . date('ymd') . '-' . strtoupper(Str::random(4));

            $po = PurchaseOrder::create([
                'nomor_po'      => $poNumber,
                'po_type'       => 'supplier_po',
                'tanggal_po'    => $request->tanggal_po,
                'supplier_id'   => $request->supplier_id,
                'lokasi_id'     => $gudangId,
                'status'        => 'PENDING_APPROVAL',
                'created_by'    => Auth::id(),
                'catatan'       => $request->catatan,
            ]);

            $totalAmount = 0;
            
            foreach ($request->items as $item) {
                if(empty($item['barang_id']) || empty($item['qty'])) continue;

                $barang = Barang::find($item['barang_id']);

                // [MODIFIKASI] VALIDASI BARANG AKTIF
                if (!$barang->is_active) {
                    throw new \Exception("Gagal Membuat PO! Barang '{$barang->part_name}' statusnya NONAKTIF.");
                }

                $hargaBeli = $barang->selling_in > 0 ? $barang->selling_in : 0;
                $subtotal = $item['qty'] * $hargaBeli;

                PurchaseOrderDetail::create([
                    'purchase_order_id' => $po->id,
                    'barang_id'         => $item['barang_id'],
                    'qty_pesan'         => $item['qty'],
                    'harga_beli'        => $hargaBeli,
                    'subtotal'          => $subtotal
                ]);
                $totalAmount += $subtotal;
            }

            $po->update(['total_amount' => $totalAmount]);

            DB::commit();
            return redirect()->route('admin.purchase-orders.index', ['type' => 'supplier_po'])
                             ->with('success', 'PO ke Supplier berhasil dibuat.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error: ' . $e->getMessage())->withInput();
        }
    }

    protected function storeDealerRequest(Request $request)
    {
        $request->validate([
            'sumber_lokasi_id' => 'required|exists:lokasi,id',
            'requests' => 'required|string',
        ]);

        $dealerRequests = json_decode($request->requests, true);

        if (empty($dealerRequests)) {
            return back()->with('error', 'Item request kosong.')->withInput();
        }

        DB::beginTransaction();
        try {
            $groupId = 'REQ-' . now()->timestamp;
            $count = 0; // Initialize count

            foreach ($dealerRequests as $req) {
                $items = $req['items'] ?? [];
                if (empty($items)) continue;

                $lokasiTujuanId = $req['lokasi_id'];
                if (Auth::user()->isDealer() && $lokasiTujuanId != Auth::user()->lokasi_id) {
                    throw new \Exception("Anda hanya boleh membuat request untuk dealer Anda sendiri.");
                }

                $subtotalPO = 0;

                $po = PurchaseOrder::create([
                    'nomor_po' => $this->generatePoNumber($lokasiTujuanId),
                    'po_type' => 'dealer_request',
                    'request_group_id' => $groupId,
                    'tanggal_po' => $request->tanggal_po,
                    'sumber_lokasi_id' => $request->sumber_lokasi_id,
                    'lokasi_id' => $lokasiTujuanId,
                    'status' => 'PENDING_APPROVAL',
                    'created_by' => Auth::id(),
                    'total_amount' => 0
                ]);

                foreach ($items as $item) {
                    $barang = Barang::find($item['barang_id']);
                    
                    // [MODIFIKASI] VALIDASI BARANG AKTIF
                    if (!$barang->is_active) {
                        throw new \Exception("Gagal Request! Barang '{$barang->part_name}' statusnya NONAKTIF.");
                    }

                    $harga = $barang->selling_out > 0 ? $barang->selling_out : 0;
                    $subtotal = $item['qty'] * $harga;

                    $po->details()->create([
                        'barang_id' => $barang->id,
                        'qty_pesan' => $item['qty'],
                        'harga_beli' => $harga,
                        'subtotal' => $subtotal
                    ]);
                    $subtotalPO += $subtotal;
                }
                
                $po->update(['total_amount' => $subtotalPO]);
                $count++;
            }

            DB::commit();
            return redirect()->route('admin.purchase-orders.index', ['type' => 'dealer_request'])
                ->with('success', "Berhasil membuat $count Request.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal: ' . $e->getMessage());
        }
    }

    private function generatePoNumber($lokasiId)
    {
        $lokasi = Lokasi::find($lokasiId);
        $kode = $lokasi ? $lokasi->kode_lokasi : 'GEN';
        $date = now()->format('ymd');
        $seq = PurchaseOrder::whereDate('created_at', today())->count() + 1;
        return "PO/{$kode}/{$date}/" . str_pad($seq, 3, '0', STR_PAD_LEFT);
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
        
        if ($purchaseOrder->status !== 'PENDING_APPROVAL') {
            return back()->with('error', 'PO sudah diproses.');
        }

        DB::beginTransaction();
        try {
            if ($purchaseOrder->po_type === 'supplier_po') {
                $purchaseOrder->update([
                    'status' => 'APPROVED',
                    'approved_by' => Auth::id(),
                    'approved_at' => now()
                ]);
            }
            elseif ($purchaseOrder->po_type === 'dealer_request') {
                $poLocked = PurchaseOrder::where('id', $purchaseOrder->id)->lockForUpdate()->first();

                foreach ($poLocked->details as $detail) {
                    $qtyApprove = $detail->qty_pesan;
                    
                    if ($qtyApprove > 0) {
                        $batches = InventoryBatch::where('lokasi_id', $poLocked->sumber_lokasi_id)
                            ->where('barang_id', $detail->barang_id)
                            ->where('quantity', '>', 0)
                            ->orderBy('created_at', 'asc')
                            ->lockForUpdate()
                            ->get();
                        
                        $sisaButuh = $qtyApprove;

                        if ($batches->sum('quantity') < $sisaButuh) {
                            throw new \Exception("Stok {$detail->barang->part_name} di Gudang Sumber tidak cukup.");
                        }

                        foreach ($batches as $batch) {
                            if ($sisaButuh <= 0) break;
                            $ambil = min($batch->quantity, $sisaButuh);
                            
                            $stokAwal = $batch->quantity;
                            $batch->decrement('quantity', $ambil);

                            StockMovement::create([
                                'barang_id' => $detail->barang_id,
                                'lokasi_id' => $poLocked->sumber_lokasi_id,
                                'rak_id'    => $batch->rak_id,
                                'jumlah'    => -$ambil,
                                'stok_sebelum' => $stokAwal,
                                'stok_sesudah' => $stokAwal - $ambil,
                                'referensi_type' => PurchaseOrder::class,
                                'referensi_id' => $poLocked->id,
                                'keterangan' => 'Transfer Out ke ' . $poLocked->lokasi->nama_lokasi,
                                'user_id' => Auth::id(),
                                'created_at' => now()
                            ]);

                            $sisaButuh -= $ambil;
                        }
                    }
                }
                
                $poLocked->update([
                    'status' => 'APPROVED', 
                    'approved_by' => Auth::id(), 
                    'approved_at' => now()
                ]);
            }

            DB::commit();
            return back()->with('success', 'PO Disetujui.');

        } catch (\Exception $e) {
            DB::rollBack();
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