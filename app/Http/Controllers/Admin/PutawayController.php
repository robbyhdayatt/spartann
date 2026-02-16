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
            ->with(['purchaseOrder.supplier', 'lokasi', 'qcBy']); // Load qcBy untuk tampilan

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

        // Cek Existing Location (Rekomendasi Rak)
        foreach ($itemsToPutaway as $item) {
            $existingBatches = InventoryBatch::where('barang_id', $item->barang_id)
                ->where('lokasi_id', $receiving->lokasi_id)
                ->where('quantity', '>', 0)
                ->with('rak')
                ->get()
                ->pluck('rak.kode_rak')
                ->unique();
            
            $item->rekomendasi_rak = $existingBatches->implode(', ');
        }

        // Ambil Rak Penyimpanan yang Aktif
        $raks = Rak::where('lokasi_id', $receiving->lokasi_id)
            ->where('tipe_rak', 'PENYIMPANAN') 
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

                // === LOCK MASTER BARANG (Hanya untuk konsistensi, tidak update harga lagi) ===
                $barang = Barang::where('id', $detail->barang_id)->lockForUpdate()->first();

                // --- LOGIKA UPDATE HARGA JUAL/BELI TELAH DIHAPUS (POIN 3) ---

                // 2. CREATE INVENTORY BATCH
                $batch = InventoryBatch::create([
                    'barang_id'           => $barang->id,
                    'rak_id'              => $data['rak_id'],
                    'lokasi_id'           => $receiving->lokasi_id,
                    'receiving_detail_id' => $detail->id,
                    'quantity'            => $qtyMasuk,
                ]);

                // 3. CATAT STOCK MOVEMENT
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