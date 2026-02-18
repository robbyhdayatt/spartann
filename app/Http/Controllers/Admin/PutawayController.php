<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryBatch;
use App\Models\Rak;
use App\Models\Receiving;
use App\Models\ReceivingDetail;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PutawayController extends Controller
{
    public function index()
    {
        $this->authorize('view-putaway');
        
        $user = Auth::user();
        $query = Receiving::where('status', 'PENDING_PUTAWAY')
            ->with(['purchaseOrder', 'lokasi']);

        if (!$user->isGlobal()) {
            $query->where('lokasi_id', $user->lokasi_id);
        }

        $receivings = $query->latest('qc_at')->paginate(15);
        return view('admin.putaway.index', compact('receivings'));
    }

    public function show(Receiving $receiving)
    {
        // Cek Gate: process-putaway-gudang ATAU process-putaway-dealer
        if (! (Auth::user()->can('process-putaway-gudang') || Auth::user()->can('process-putaway-dealer'))) {
            abort(403);
        }

        if ($receiving->status !== 'PENDING_PUTAWAY') {
            return redirect()->route('admin.putaway.index')->with('error', 'Status tidak valid.');
        }

        if (!Auth::user()->isGlobal() && Auth::user()->lokasi_id != $receiving->lokasi_id) {
            abort(403);
        }

        // Load item yang Qty Lolos > 0
        $receiving->load('details.barang');
        $itemsToPutaway = $receiving->details->filter(fn($d) => $d->qty_lolos_qc > 0);

        // Ambil Rak Penyimpanan di lokasi ini
        $raks = Rak::where('lokasi_id', $receiving->lokasi_id)
            ->where('tipe_rak', 'PENYIMPANAN')
            ->where('is_active', true)
            ->orderBy('kode_rak')
            ->get();

        return view('admin.putaway.form', compact('receiving', 'itemsToPutaway', 'raks'));
    }

    public function store(Request $request, Receiving $receiving)
    {
        // Validasi Gate
        if (! (Auth::user()->can('process-putaway-gudang') || Auth::user()->can('process-putaway-dealer'))) {
            abort(403);
        }

        $request->validate([
            'items' => 'required|array',
            'items.*.rak_id' => 'required|exists:raks,id',
        ]);

        DB::beginTransaction();
        try {
            $receiving = Receiving::where('id', $receiving->id)->lockForUpdate()->first();
            
            if ($receiving->status !== 'PENDING_PUTAWAY') throw new \Exception("Status berubah.");

            foreach ($request->items as $detailId => $data) {
                $detail = ReceivingDetail::findOrFail($detailId);
                $qty = $detail->qty_lolos_qc;

                if ($qty <= 0) continue;

                // Masuk ke Batch Rak
                $batch = InventoryBatch::create([
                    'barang_id' => $detail->barang_id,
                    'rak_id'    => $data['rak_id'],
                    'lokasi_id' => $receiving->lokasi_id,
                    'receiving_detail_id' => $detail->id,
                    'quantity'  => $qty
                ]);

                // Catat Movement
                // (Optional: Cek stok rak sebelumnya untuk log)
                
                StockMovement::create([
                    'barang_id' => $detail->barang_id,
                    'lokasi_id' => $receiving->lokasi_id,
                    'rak_id'    => $data['rak_id'],
                    'jumlah'    => $qty,
                    'stok_sebelum' => 0, // Asumsi batch baru selalu 0 startnya (FIFO strict)
                    'stok_sesudah' => $qty,
                    'referensi_type' => Receiving::class,
                    'referensi_id'   => $receiving->id,
                    'keterangan'     => "Putaway PO " . ($receiving->purchaseOrder->nomor_po ?? ''),
                    'user_id'        => Auth::id()
                ]);
            }

            // Finish
            $receiving->status = 'COMPLETED';
            $receiving->putaway_by = Auth::id();
            $receiving->putaway_at = now();
            $receiving->save();

            // Sync PO (Jika full received, update status PO juga)
            if ($receiving->purchaseOrder) {
                $receiving->purchaseOrder->syncStatus();
            }

            DB::commit();
            return redirect()->route('admin.putaway.index')->with('success', 'Putaway Selesai. Stok Bertambah.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }
}