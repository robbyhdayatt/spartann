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
                            ->with(['purchaseOrder.supplier', 'purchaseOrder.sumberLokasi', 'lokasi']); 

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
            return redirect()->route('admin.putaway.index')->with('error', 'Dokumen ini tidak siap untuk Putaway.');
        }

        if (Auth::user()->lokasi_id != $receiving->lokasi_id && !Auth::user()->hasRole(['SA', 'PIC'])) {
            return redirect()->route('admin.putaway.index')->with('error', 'Akses ditolak.');
        }

        $receiving->load('details.barang');

        $raks = Rak::where('lokasi_id', $receiving->lokasi_id)
                    ->where('is_active', true)
                    ->where('tipe_rak', 'PENYIMPANAN')
                    ->orderBy('kode_rak')
                    ->get();

        $itemsToPutaway = $receiving->details()->where('qty_lolos_qc', '>', 0)->get();

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
            // Lock Receiving agar tidak diproses double
            $receiving = Receiving::where('id', $receiving->id)->lockForUpdate()->first();
            
            if ($receiving->status == 'COMPLETED') {
                 throw new \Exception("Receiving ini sudah selesai diproses.");
            }

            foreach ($request->items as $detailId => $data) {
                $detail = ReceivingDetail::findOrFail($detailId);
                
                // === LOCK MASTER BARANG (PENTING UNTUK HPP) ===
                $barang = Barang::where('id', $detail->barang_id)->lockForUpdate()->first();
                
                $jumlahMasuk = $detail->qty_lolos_qc;
                if ($jumlahMasuk <= 0) continue;

                // 1. Cek Stok Lama (Untuk History)
                $stokSebelumDiRak = InventoryBatch::where('lokasi_id', $receiving->lokasi_id)
                    ->where('rak_id', $data['rak_id'])
                    ->where('barang_id', $detail->barang_id)
                    ->sum('quantity');

                // 2. Buat Batch Inventory Baru
                InventoryBatch::create([
                    'barang_id'           => $detail->barang_id, 
                    'rak_id'              => $data['rak_id'],
                    'lokasi_id'           => $receiving->lokasi_id,
                    'receiving_detail_id' => $detail->id,
                    'quantity'            => $jumlahMasuk,
                ]);

                // 3. Update HPP (Average Cost) Aman karena Barang sudah di-LOCK
                if ($receiving->purchaseOrder && $receiving->purchaseOrder->po_type == 'supplier_po') {
                    $poDetail = PurchaseOrderDetail::where('purchase_order_id', $receiving->purchase_order_id)
                                                ->where('barang_id', $barang->id)
                                                ->first();

                    $hargaBeliBaru = $poDetail ? $poDetail->harga_beli : $barang->selling_in;
                    
                    $allBatches = InventoryBatch::where('barang_id', $barang->id)->get();
                    $totalStokSekarang = $allBatches->sum('quantity'); 
                    
                    $stokLama = $totalStokSekarang - $jumlahMasuk; 
                    if ($stokLama < 0) $stokLama = 0; 

                    $nilaiAsetLama = $stokLama * $barang->selling_in;
                    $nilaiAsetBaru = $jumlahMasuk * $hargaBeliBaru;

                    $sellingInBaru = ($totalStokSekarang > 0)
                        ? (($nilaiAsetLama + $nilaiAsetBaru) / $totalStokSekarang)
                        : $hargaBeliBaru;

                    $barang->update(['selling_in' => $sellingInBaru]);
                }

                // 4. Catat Movement
                $stokSesudahDiRak = $stokSebelumDiRak + $jumlahMasuk;

                $receiving->stockMovements()->create([
                    'barang_id'    => $detail->barang_id,
                    'lokasi_id'    => $receiving->lokasi_id,
                    'rak_id'       => $data['rak_id'],
                    'jumlah'       => $jumlahMasuk,
                    'stok_sebelum' => $stokSebelumDiRak, 
                    'stok_sesudah' => $stokSesudahDiRak, 
                    'user_id'      => Auth::id(),
                    'keterangan'   => 'Putaway PO ' . ($receiving->purchaseOrder->nomor_po ?? '-'),
                ]);

                $detail->update(['qty_disimpan' => $jumlahMasuk]);
            }

            // 5. Update Status Receiving & PO
            $statusAkhirReceiving = 'COMPLETED'; 

            if($receiving->purchaseOrder) {
                $receiving->purchaseOrder->syncStatus();
                $receiving->purchaseOrder->refresh(); 

                if ($receiving->purchaseOrder->status === 'FULLY_RECEIVED') {
                    $statusAkhirReceiving = 'COMPLETED';
                    // Update saudara-saudaranya
                    Receiving::where('purchase_order_id', $receiving->purchaseOrder->id)
                             ->where('status', 'PARTIAL_CLOSED')
                             ->where('id', '!=', $receiving->id)
                             ->update(['status' => 'COMPLETED']);
                } else {
                    $statusAkhirReceiving = 'PARTIAL_CLOSED';
                }
            }

            $receiving->status = $statusAkhirReceiving;
            $receiving->putaway_by = Auth::id();
            $receiving->putaway_at = now();
            $receiving->save();

            DB::commit();
            return redirect()->route('admin.putaway.index')->with('success', 'Putaway berhasil. Stok dan Status PO diperbarui.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error: ' . $e->getMessage())->withInput();
        }
    }
}