<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Models\Lokasi;
use App\Models\Barang;
use App\Models\Supplier;
use App\Models\User;
use App\Models\InventoryBatch;
use App\Models\StockMovement; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDF; 

class PurchaseOrderController extends Controller
{
    // --- 1. DAFTAR PO ---
    public function index(Request $request)
    {
        $type = $request->get('type', 'dealer_request'); 
        
        $purchaseOrders = PurchaseOrder::with(['lokasi', 'supplier', 'createdBy', 'sumberLokasi'])
            ->where('po_type', $type)
            ->latest()
            ->get();

        return view('admin.purchase_orders.index', compact('purchaseOrders', 'type'));
    }

    // --- 2. HALAMAN BUAT PO BARU ---
    public function create()
    {
        $this->authorize('create-po');
        $user = Auth::user();
        $barangs = Barang::orderBy('part_name')->get();

        // --- SKENARIO 1: SERVICE MD / DEALER ---
        if ($user->hasRole(['SMD', 'SA', 'PIC', 'PC']) || ($user->lokasi && $user->lokasi->tipe === 'DEALER')) {
            $sumberPusat = Lokasi::where('tipe', 'PUSAT')->first();
            
            if ($user->hasRole(['SMD', 'SA', 'PIC']) || ($user->lokasi && $user->lokasi->tipe === 'PUSAT')) {
                $dealers = Lokasi::where('tipe', 'DEALER')->where('is_active', true)->orderBy('nama_lokasi')->get();
            } else {
                $dealers = Lokasi::where('id', $user->lokasi_id)->get();
            }

            return view('admin.purchase_orders.create_request', compact('sumberPusat', 'dealers', 'barangs'));
        }

        // --- SKENARIO 2: ADMIN GUDANG ---
        $suppliers = Supplier::all();
        return view('admin.purchase_orders.create_supplier', compact('suppliers', 'barangs'));
    }

    // --- 3. SIMPAN PO (STORE) ---
    public function store(Request $request)
    {
        $this->authorize('create-po');

        $request->validate([
            'tanggal_po' => 'required|date',
            'po_type'    => 'required|in:dealer_request,supplier_po',
        ]);

        if ($request->po_type === 'supplier_po') {
            return $this->storeSupplierPO($request);
        } else {
            return $this->storeDealerRequest($request);
        }
    }

    // --- 3a. LOGIKA STORE: GUDANG KE SUPPLIER ---
    protected function storeSupplierPO(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'items'       => 'required|array',
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

                $barang = Barang::findOrFail($item['barang_id']);
                $hargaBeli = $barang->selling_in ?? 0;
                $subtotal = $item['qty'] * $hargaBeli;

                PurchaseOrderDetail::create([
                    'purchase_order_id' => $po->id,
                    'barang_id'         => $barang->id,
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

    // --- 3b. LOGIKA STORE: DEALER REQUEST ---
    protected function storeDealerRequest(Request $request)
    {
        $request->validate([
            'sumber_lokasi_id' => 'required|exists:lokasi,id',
            'requests' => 'required|string',
        ]);

        $dealerRequests = json_decode($request->requests, true);

        if (empty($dealerRequests)) {
            return back()->with('error', 'Tidak ada item yang direquest.')->withInput();
        }

        DB::beginTransaction();
        try {
            $requestGroupId = 'REQ-' . now()->format('ymdHis') . '-' . strtoupper(Str::random(3));
            $successCount = 0;

            foreach ($dealerRequests as $dealerReq) {
                $lokasiTujuanId = $dealerReq['lokasi_id'];
                $items = $dealerReq['items'];

                if (empty($items)) continue;

                $subtotalPO = 0;
                $detailsToInsert = [];

                foreach ($items as $item) {
                    $barang = Barang::find($item['barang_id']);
                    $hargaSatuan = $barang->selling_out > 0 ? $barang->selling_out : $barang->selling_in;
                    
                    $subtotalItem = $item['qty'] * $hargaSatuan;
                    $subtotalPO += $subtotalItem;

                    $detailsToInsert[] = [
                        'barang_id' => $barang->id,
                        'qty_pesan' => $item['qty'],
                        'harga_beli' => $hargaSatuan, 
                        'subtotal' => $subtotalItem,
                    ];
                }

                $po = PurchaseOrder::create([
                    'nomor_po' => $this->generatePoNumber($lokasiTujuanId),
                    'po_type' => 'dealer_request',
                    'request_group_id' => $requestGroupId,
                    'tanggal_po' => $request->tanggal_po,
                    'sumber_lokasi_id' => $request->sumber_lokasi_id,
                    'supplier_id' => null,
                    'lokasi_id' => $lokasiTujuanId,
                    'status' => 'PENDING_APPROVAL',
                    'total_amount' => $subtotalPO,
                    'created_by' => Auth::id(),
                    'catatan' => 'Request by ' . Auth::user()->name . ' (Group: ' . $requestGroupId . ')',
                ]);

                $po->details()->createMany($detailsToInsert);
                $successCount++;
            }

            DB::commit();
            return redirect()->route('admin.purchase-orders.index', ['type' => 'dealer_request'])
                             ->with('success', "Berhasil membuat $successCount Request PO Dealer.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menyimpan: ' . $e->getMessage())->withInput();
        }
    }

    private function generatePoNumber($lokasiId)
    {
        $lokasi = Lokasi::find($lokasiId);
        $kodeLokasi = $lokasi ? $lokasi->kode_lokasi : 'GEN';
        $date = now()->format('ymd');

        $count = PurchaseOrder::where('lokasi_id', $lokasiId)
                              ->whereDate('created_at', today())
                              ->count();
        $sequence = str_pad($count + 1, 3, '0', STR_PAD_LEFT);

        return "PO/{$kodeLokasi}/{$date}/{$sequence}";
    }

    // --- 4. DETAIL PO (SHOW) ---
    public function show($id)
    {
        $purchaseOrder = PurchaseOrder::with([
            'lokasi', 
            'sumberLokasi', 
            'details.barang', 
            'createdBy', 
            'approvedBy', 
            'approvedByHead'
        ])->findOrFail($id);

        if ($purchaseOrder->po_type === 'dealer_request') {
            $gudangSumberId = $purchaseOrder->sumber_lokasi_id;

            foreach ($purchaseOrder->details as $detail) {
                $stokFisik = InventoryBatch::where('lokasi_id', $gudangSumberId)
                    ->where('barang_id', $detail->barang_id)
                    ->sum('quantity');

                $stokMin = $detail->barang->stok_minimum ?? 0;

                $detail->stok_aktual_gudang = $stokFisik;
                $detail->stok_minimum_barang = $stokMin;
                
                $sisaSetelahKeluar = $stokFisik - $detail->qty_pesan;
                $detail->sisa_prediksi = $sisaSetelahKeluar;
                
                $detail->is_stock_safe = ($sisaSetelahKeluar >= $stokMin);
            }
        }
                                      
        return view('admin.purchase_orders.show', compact('purchaseOrder'));
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder) 
    {
        $this->authorize('approve-po', $purchaseOrder);
        $user = Auth::user();

        if ($purchaseOrder->po_type === 'supplier_po') {
            $purchaseOrder->update([
                'status' => 'APPROVED',
                'approved_by_head_id' => $user->id,
                'approved_by_head_at' => now(),
            ]);
            return back()->with('success', 'PO Supplier Disetujui.');
        }

        // --- LOGIKA APPROVE REQUEST DEALER (Internal Transfer) ---
        if ($purchaseOrder->po_type === 'dealer_request') {
            
            $request->validate([
                'qty_approved' => 'required|array',
                'qty_approved.*' => 'required|numeric|min:0',
            ]);

            DB::beginTransaction();
            try {
                $gudangSumberId = $purchaseOrder->sumber_lokasi_id;
                $totalAmountBaru = 0; 

                // 1. UPDATE QTY SESUAI INPUT
                foreach ($purchaseOrder->details as $detail) {
                    $barang = $detail->barang;
                    $qtyDisetujui = $request->qty_approved[$detail->id] ?? $detail->qty_pesan;
                    
                    // Update Detail PO 
                    if ($qtyDisetujui != $detail->qty_pesan) {
                        $subtotalBaru = $qtyDisetujui * $detail->harga_beli;
                        $detail->update([
                            'qty_pesan' => $qtyDisetujui,
                            'subtotal'  => $subtotalBaru
                        ]);
                    }
                    $totalAmountBaru += ($qtyDisetujui * $detail->harga_beli);
                }

                $purchaseOrder->update(['total_amount' => $totalAmountBaru]);

                // 2. EKSEKUSI PENGURANGAN STOK (FIFO) - DENGAN LOCKING
                foreach ($purchaseOrder->details as $detail) {
                    $qtySisaPotong = $detail->qty_pesan; 
                    
                    if ($qtySisaPotong == 0) continue; 

                    // === ANTI RACE CONDITION: LOCK FOR UPDATE ===
                    $batches = InventoryBatch::where('lokasi_id', $gudangSumberId)
                        ->where('barang_id', $detail->barang_id)
                        ->where('quantity', '>', 0)
                        ->orderBy('created_at', 'asc')
                        ->lockForUpdate() // <--- LOCKING PENTING
                        ->get();

                    // Cek Total Stok Fisik Lagi (Double Check)
                    if ($batches->sum('quantity') < $qtySisaPotong) {
                        throw new \Exception("Stok {$detail->barang->part_name} tidak mencukupi saat diproses. Transaksi dibatalkan.");
                    }

                    foreach ($batches as $batch) {
                        if ($qtySisaPotong <= 0) break;

                        $stokSebelumPotong = $batch->quantity;

                        if ($batch->quantity >= $qtySisaPotong) {
                            $batch->decrement('quantity', $qtySisaPotong);
                            
                            $this->createStockMovement($detail, $gudangSumberId, $batch->rak_id, $qtySisaPotong, $stokSebelumPotong, $purchaseOrder, $user);
                            
                            $qtySisaPotong = 0;
                        } else {
                            $qtyDiambil = $batch->quantity;
                            
                            $this->createStockMovement($detail, $gudangSumberId, $batch->rak_id, $qtyDiambil, $stokSebelumPotong, $purchaseOrder, $user);

                            $qtySisaPotong -= $qtyDiambil;
                            $batch->update(['quantity' => 0]);
                        }
                    }
                }

                $purchaseOrder->update([
                    'status' => 'APPROVED',
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                ]);

                DB::commit();
                return back()->with('success', 'Request Dealer Disetujui. Stok dipotong aman (Locked).');

            } catch (\Exception $e) {
                DB::rollBack();
                return back()->with('error', $e->getMessage());
            }
        }
    }

    private function createStockMovement($detail, $lokasiId, $rakId, $jumlah, $stokSebelum, $po, $user) 
    {
        StockMovement::create([
            'barang_id' => $detail->barang_id,
            'lokasi_id' => $lokasiId,
            'rak_id'    => $rakId,
            'jumlah'    => -$jumlah,
            'stok_sebelum' => $stokSebelum,
            'stok_sesudah' => $stokSebelum - $jumlah,
            'user_id'   => $user->id,
            'referensi_type' => PurchaseOrder::class,
            'referensi_id'   => $po->id,
            'keterangan' => 'Transfer Keluar ke ' . $po->lokasi->nama_lokasi,
            'created_at' => now(),
        ]);
    }

    public function reject(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorize('approve-po', $purchaseOrder);
        $request->validate(['rejection_reason' => 'required|string|min:5']);

        $purchaseOrder->update([
            'status' => 'REJECTED',
            'rejection_reason' => $request->rejection_reason,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', 'PO berhasil ditolak.');
    }

    public function pdf(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['lokasi', 'sumberLokasi', 'details.barang', 'createdBy', 'approvedBy', 'approvedByHead']);
        
        $pdf = PDF::loadView('admin.purchase_orders.print', compact('purchaseOrder'));
        $pdf->setPaper('a4', 'portrait');
        
        return $pdf->stream('PO-' . $purchaseOrder->nomor_po . '.pdf');
    }
}