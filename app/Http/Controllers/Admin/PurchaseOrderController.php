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
        // 1. Authorize Akses Menu (Sesuai Poin 9)
        $this->authorize('view-po'); 

        // 2. Tentukan Default Tab berdasarkan Role
        $defaultType = 'dealer_request';
        
        // Jika Orang Gudang (AG/KG) -> Default lihat PO Supplier (Tugas Utama mereka)
        // Jika Orang Pusat (IMS/ACC) -> Default lihat Dealer Request (Tugas Utama mereka)
        if (Auth::user()->isGudang()) { // Menggunakan helper isGudang() agar lebih aman
            $defaultType = 'supplier_po';
        }

        $type = $request->get('type', $defaultType);
        
        // 3. Query dengan Filter Hak Akses
        $query = PurchaseOrder::with(['lokasi', 'supplier', 'createdBy', 'sumberLokasi'])
            ->where('po_type', $type)
            ->latest();

        // 4. Filter Tambahan: GUDANG tidak boleh lihat Dealer Request (Masuk)? 
        // Sesuai Poin 9: ASD & ACC (Pusat) view PO Dealer. AG approve PO Dealer.
        // Jadi AG *harus* bisa lihat Dealer Request untuk approve.
        
        // Namun, jika Dealer (PC) login, dia hanya boleh lihat PO miliknya sendiri.
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

        // SCENARIO 1: DEALER REQUEST (IMS Pusat & PC Dealer)
        // IMS membuatkan request untuk banyak dealer sekaligus.
        // PC membuat request untuk dirinya sendiri.
        if ($user->can('create-po-dealer') || ($user->isDealer() && $user->hasRole('PC'))) {
            
            // Sumber Barang = Gudang Pusat (Fisik)
            $sumberPusat = Lokasi::where('tipe', 'GUDANG')->first(); // Ubah ke GUDANG PART sesuai struktur baru
            if(!$sumberPusat) $sumberPusat = Lokasi::where('tipe', 'PUSAT')->first(); // Fallback

            // List Dealer Tujuan
            if ($user->isGlobal() || $user->isPusat()) {
                // Pusat bisa pilih semua dealer
                $dealers = Lokasi::where('tipe', 'DEALER')->where('is_active', true)->orderBy('nama_lokasi')->get();
            } else {
                // Dealer PC hanya bisa pilih dirinya sendiri
                $dealers = Lokasi::where('id', $user->lokasi_id)->get();
            }

            return view('admin.purchase_orders.create_request', compact('sumberPusat', 'dealers', 'barangs'));
        }

        // SCENARIO 2: SUPPLIER PO (Admin Gudang AG)
        if ($user->can('create-po-supplier')) {
            $suppliers = Supplier::where('is_active', true)->get();
            return view('admin.purchase_orders.create_supplier', compact('suppliers', 'barangs'));
        }

        abort(403, 'Akses Ditolak.');
    }

    public function store(Request $request)
    {
        $this->authorize('create-po'); // General Check

        $request->validate([
            'tanggal_po' => 'required|date',
            'po_type'    => 'required|in:dealer_request,supplier_po',
        ]);

        if ($request->po_type === 'supplier_po') {
            // Cek Hak Akses Spesifik
            if (Auth::user()->cannot('create-po-supplier')) abort(403);
            return $this->storeSupplierPO($request);
        } else {
            // Cek Hak Akses Spesifik
            // IMS (Pusat) atau PC (Dealer)
            // if (Auth::user()->cannot('create-po-dealer') && !Auth::user()->isDealer()) abort(403);
            return $this->storeDealerRequest($request);
        }
    }

    // --- LOGIKA STORE: SUPPLIER PO (Gudang Order ke Vendor) ---
    protected function storeSupplierPO(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'items'       => 'required|array',
            'items.*.barang_id' => 'required|exists:barangs,id',
            'items.*.qty'       => 'required|integer|min:1',
        ]);

        // Lokasi penerima adalah lokasi si pembuat (Gudang Part)
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

                // Ambil harga Selling In (Beli) dari Master Barang
                $barang = Barang::find($item['barang_id']);
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

    // --- LOGIKA STORE: DEALER REQUEST (Distribusi Stok) ---
    protected function storeDealerRequest(Request $request)
    {
        $request->validate([
            'sumber_lokasi_id' => 'required|exists:lokasi,id',
            'requests' => 'required|string', // JSON String dari view
        ]);

        $dealerRequests = json_decode($request->requests, true);

        if (empty($dealerRequests)) {
            return back()->with('error', 'Item request kosong.')->withInput();
        }

        DB::beginTransaction();
        try {
            $groupId = 'REQ-' . now()->timestamp; // Grouping ID agar bisa ditrack batch-nya
            $count = 0;

            foreach ($dealerRequests as $req) {
                $items = $req['items'] ?? [];
                if (empty($items)) continue;

                $lokasiTujuanId = $req['lokasi_id'];
                
                // Security Check: Jika User adalah PC Dealer, pastikan dia tidak request untuk dealer lain via inspect element
                if (Auth::user()->isDealer() && $lokasiTujuanId != Auth::user()->lokasi_id) {
                    throw new \Exception("Anda hanya boleh membuat request untuk dealer Anda sendiri.");
                }

                $subtotalPO = 0;

                // Buat Header PO (Satu PO per Dealer Tujuan)
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
                    // Harga Transfer Internal: Gunakan Selling Out (Harga Jual ke Dealer)
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
        // Count PO hari ini untuk generate sequence
        $seq = PurchaseOrder::whereDate('created_at', today())->count() + 1;
        return "PO/{$kode}/{$date}/" . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }

    public function show($id)
    {
        $this->authorize('view-po');
        
        $purchaseOrder = PurchaseOrder::with(['lokasi', 'sumberLokasi', 'details.barang', 'createdBy', 'approvedBy'])
            ->findOrFail($id);
            
        // Tambahkan info stok gudang sumber jika dealer request (Untuk pertimbangan approval)
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
            // APPROVAL SUPPLIER PO (Oleh KG)
            if ($purchaseOrder->po_type === 'supplier_po') {
                $purchaseOrder->update([
                    'status' => 'APPROVED',
                    'approved_by' => Auth::id(),
                    'approved_at' => now()
                ]);
                // Stok belum bertambah disini. Nanti saat Receiving.
            } 
            // APPROVAL DEALER REQUEST (Oleh AG)
            // Sistem akan otomatis memotong stok Gudang Pusat dan memindahkannya ke status "Transit" (atau langsung pindah, tergantung flow).
            // Flow disini: Potong Stok Sumber -> Catat Movement Keluar. (Stok Masuk dealer saat Receiving).
            elseif ($purchaseOrder->po_type === 'dealer_request') {
                
                // AG bisa edit Qty Approve jika stok kurang (Partial Approve)
                // $request->validate(['qty_approved' => 'required|array']); // Opsional jika ada input
                
                // === LOCKING ROW ===
                $poLocked = PurchaseOrder::where('id', $purchaseOrder->id)->lockForUpdate()->first();

                foreach ($poLocked->details as $detail) {
                    $qtyApprove = $detail->qty_pesan; // Default full approve. Bisa diganti logic partial.
                    
                    if ($qtyApprove > 0) {
                        // Ambil batch dari Gudang Sumber (FIFO)
                        $batches = InventoryBatch::where('lokasi_id', $poLocked->sumber_lokasi_id)
                            ->where('barang_id', $detail->barang_id)
                            ->where('quantity', '>', 0)
                            ->orderBy('created_at', 'asc')
                            ->lockForUpdate()
                            ->get();
                        
                        $sisaButuh = $qtyApprove;
                        
                        // Cek Stok Cukup
                        if ($batches->sum('quantity') < $sisaButuh) {
                            throw new \Exception("Stok {$detail->barang->part_name} di Gudang Sumber tidak cukup.");
                        }

                        foreach ($batches as $batch) {
                            if ($sisaButuh <= 0) break;
                            $ambil = min($batch->quantity, $sisaButuh);
                            
                            $stokAwal = $batch->quantity;
                            $batch->decrement('quantity', $ambil);

                            // Catat Mutasi Keluar dari Sumber (Transit Out)
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