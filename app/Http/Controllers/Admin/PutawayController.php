<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryBatch;
use App\Models\Lokasi; 
use App\Models\Barang;
use App\Models\PurchaseOrderDetail;
use App\Models\Rak;
use App\Models\Receiving;
use App\Models\ReceivingDetail;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PutawayController extends Controller
{
    public function index()
    {
        $this->authorize('perform-warehouse-ops');
        $user = Auth::user();

        $query = Receiving::where('status', 'PENDING_PUTAWAY')
            ->with(['purchaseOrder.supplier', 'lokasi']); 

        if (!$user->hasRole(['SA', 'PIC', 'MA'])) {
            $query->where('lokasi_id', $user->lokasi_id);
        }

        $receivings = $query->latest('qc_at')->paginate(15);

        return view('admin.putaway.index', compact('receivings'));
    }

    public function showPutawayForm(Receiving $receiving)
    {
        $this->authorize('perform-warehouse-ops');
        
        if ($receiving->status !== 'PENDING_PUTAWAY') {
            return redirect()->route('admin.putaway.index')->with('error', 'Status dokumen tidak valid.');
        }

        if (Auth::user()->lokasi_id && Auth::user()->lokasi_id != $receiving->lokasi_id) {
            return redirect()->route('admin.putaway.index')->with('error', 'Akses Ditolak.');
        }

        $receiving->load('details.barang');
        
        // Filter: Hanya ambil item yang lolos QC > 0
        $itemsToPutaway = $receiving->details->filter(function($d) {
            return $d->qty_lolos_qc > 0;
        });

        // Ambil Rak Penyimpanan yang Aktif
        $raks = Rak::where('lokasi_id', $receiving->lokasi_id)
            ->where('tipe_rak', 'PENYIMPANAN') // Filter tipe rak penting!
            ->where('is_active', true)
            ->orderBy('kode_rak')
            ->get();

        return view('admin.putaway.form', compact('receiving', 'itemsToPutaway', 'raks'));
    }

    public function storePutaway(Request $request, Receiving $receiving)
    {
        $this->authorize('perform-warehouse-ops');
        
        $request->validate([
            'items' => 'required|array',
            'items.*.rak_id' => 'required|exists:raks,id',
        ]);

        DB::beginTransaction();
        try {
            // Lock Receiving
            $receiving = Receiving::where('id', $receiving->id)->lockForUpdate()->first();

            if ($receiving->status !== 'PENDING_PUTAWAY') {
                throw new \Exception("Dokumen sudah diproses/berubah status.");
            }

            foreach ($request->items as $detailId => $data) {
                $detail = ReceivingDetail::findOrFail($detailId);
                $qtyMasuk = $detail->qty_lolos_qc;

                if ($qtyMasuk <= 0) continue;

                // === LOCK MASTER BARANG UNTUK UPDATE HPP ===
                $barang = Barang::where('id', $detail->barang_id)->lockForUpdate()->first();

                // 1. UPDATE HPP (AVERAGE COST)
                // Hanya jika ini dari Supplier PO (bukan transfer antar cabang)
                if ($receiving->purchaseOrder && $receiving->purchaseOrder->po_type == 'supplier_po') {
                    // Ambil harga beli dari PO
                    $poDetail = PurchaseOrderDetail::where('purchase_order_id', $receiving->purchase_order_id)
                        ->where('barang_id', $barang->id)
                        ->first();
                        
                    $hargaBeliBaru = $poDetail ? $poDetail->harga_beli : 0;
                    
                    if ($hargaBeliBaru > 0) {
                        // Hitung Weighted Average
                        $currentTotalStock = InventoryBatch::where('barang_id', $barang->id)->sum('quantity'); // Global stock for HPP? Atau per lokasi? Biasanya Global.
                        
                        $nilaiAsetLama = $currentTotalStock * $barang->selling_in; // selling_in = HPP
                        $nilaiAsetBaru = $qtyMasuk * $hargaBeliBaru;
                        $totalQtyBaru = $currentTotalStock + $qtyMasuk;
                        
                        $newHpp = ($nilaiAsetLama + $nilaiAsetBaru) / $totalQtyBaru;
                        
                        $barang->update(['selling_in' => $newHpp]);
                    }
                }

                // 2. CREATE INVENTORY BATCH
                $batch = InventoryBatch::create([
                    'barang_id'           => $barang->id,
                    'rak_id'              => $data['rak_id'],
                    'lokasi_id'           => $receiving->lokasi_id,
                    'receiving_detail_id' => $detail->id,
                    'quantity'            => $qtyMasuk,
                    // 'harga_beli'       => $hargaBeliBaru // Jika ingin track harga per batch
                ]);

                // 3. CATAT STOCK MOVEMENT
                // Kita perlu snapshot stok sebelumnya di rak tersebut untuk log yang akurat
                // Tapi karena InventoryBatch baru di-create (bukan update), stok sebelumnya untuk *batch ini* adalah 0.
                // Namun untuk *Rak tersebut*, stok sebelumnya mungkin ada. 
                // StockMovement mencatat stok agregat per Rak/Barang atau per Transaksi? 
                // Standarnya per transaksi (flow).
                
                // Hitung stok akumulasi di Rak itu sebelum insert (untuk reporting)
                $stokRakSebelum = InventoryBatch::where('rak_id', $data['rak_id'])
                    ->where('barang_id', $barang->id)
                    ->where('id', '!=', $batch->id)
                    ->sum('quantity');

                StockMovement::create([
                    'barang_id'      => $barang->id,
                    'lokasi_id'      => $receiving->lokasi_id,
                    'rak_id'         => $data['rak_id'],
                    'jumlah'         => $qtyMasuk,
                    'stok_sebelum'   => $stokRakSebelum,
                    'stok_sesudah'   => $stokRakSebelum + $qtyMasuk,
                    'referensi_type' => Receiving::class,
                    'referensi_id'   => $receiving->id,
                    'keterangan'     => "Putaway PO #{$receiving->purchaseOrder->nomor_po}",
                    'user_id'        => Auth::id()
                ]);
            }

            // 4. UPDATE STATUS RECEIVING & PO
            $receiving->status = 'COMPLETED';
            $receiving->putaway_by = Auth::id();
            $receiving->putaway_at = now();
            $receiving->save();

            // Sync PO Status
            if ($receiving->purchaseOrder) {
                $receiving->purchaseOrder->syncStatus();
                
                // Cek apakah PO sudah Fully Received, jika ya, tutup semua receiving parsial lain
                if ($receiving->purchaseOrder->status === 'FULLY_RECEIVED') {
                    Receiving::where('purchase_order_id', $receiving->purchase_order_id)
                        ->where('status', 'PARTIAL_CLOSED')
                        ->update(['status' => 'COMPLETED']);
                }
            }

            DB::commit();
            return redirect()->route('admin.putaway.index')->with('success', 'Barang berhasil disimpan ke Rak. Stok telah bertambah.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error: ' . $e->getMessage())->withInput();
        }
    }
}