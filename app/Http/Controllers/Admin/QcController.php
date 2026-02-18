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

class QcController extends Controller
{
    public function index()
    {
        // Hanya AG/Global
        $this->authorize('view-qc'); 
        
        $user = Auth::user();
        $query = Receiving::where('status', 'PENDING_QC')
            ->with(['purchaseOrder.supplier', 'lokasi']);

        if (!$user->isGlobal()) {
            $query->where('lokasi_id', $user->lokasi_id);
        }

        $receivings = $query->latest('tanggal_terima')->paginate(15);
        return view('admin.qc.index', compact('receivings'));
    }

    public function show(Receiving $receiving)
    {
        $this->authorize('process-qc'); // Gate khusus proses

        if ($receiving->status !== 'PENDING_QC') {
            return redirect()->route('admin.qc.index')->with('error', 'Status dokumen tidak valid.');
        }
        
        if (Auth::user()->lokasi_id && Auth::user()->lokasi_id != $receiving->lokasi_id) {
            abort(403);
        }

        $receiving->load(['details.barang']);
        return view('admin.qc.form', compact('receiving'));
    }

    public function store(Request $request, Receiving $receiving)
    {
        $this->authorize('process-qc');

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.qty_lolos' => 'required|integer|min:0',
            'items.*.qty_gagal' => 'required|integer|min:0',
            'items.*.catatan_qc' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $receiving = Receiving::where('id', $receiving->id)->lockForUpdate()->first();

            if ($receiving->status !== 'PENDING_QC') throw new \Exception("Status berubah.");

            $totalLolosOverall = 0;
            $hasFailure = collect($validated['items'])->sum('qty_gagal') > 0;
            $quarantineRak = null;

            // Jika ada barang gagal, pastikan Rak Karantina ada
            if ($hasFailure) {
                $quarantineRak = Rak::where('lokasi_id', $receiving->lokasi_id)
                    ->where('tipe_rak', 'KARANTINA')->first();
                
                if (!$quarantineRak) throw new \Exception("Rak Karantina belum dibuat di lokasi ini.");
            }

            foreach ($validated['items'] as $detailId => $data) {
                $detail = ReceivingDetail::findOrFail($detailId);
                
                $qtyLolos = (int)$data['qty_lolos'];
                $qtyGagal = (int)$data['qty_gagal'];
                
                // Validasi Total
                if (($qtyLolos + $qtyGagal) != $detail->qty_terima) {
                    throw new \Exception("Jumlah Lolos + Gagal tidak sama dengan Qty Terima pada item ID $detailId");
                }

                $detail->update([
                    'qty_lolos_qc' => $qtyLolos,
                    'qty_gagal_qc' => $qtyGagal,
                    'catatan_qc'   => $data['catatan_qc']
                ]);

                // Handle Barang Gagal -> Masuk Karantina
                if ($qtyGagal > 0) {
                    $batch = InventoryBatch::firstOrNew([
                        'barang_id' => $detail->barang_id,
                        'rak_id'    => $quarantineRak->id,
                        'lokasi_id' => $receiving->lokasi_id,
                    ]);
                    
                    $stokAwal = $batch->quantity ?? 0;
                    $batch->quantity = $stokAwal + $qtyGagal;
                    $batch->save();

                    StockMovement::create([
                        'barang_id' => $detail->barang_id,
                        'lokasi_id' => $receiving->lokasi_id,
                        'rak_id'    => $quarantineRak->id,
                        'jumlah'    => $qtyGagal,
                        'stok_sebelum' => $stokAwal,
                        'stok_sesudah' => $batch->quantity,
                        'referensi_type' => Receiving::class,
                        'referensi_id'   => $receiving->id,
                        'keterangan'     => "QC Reject (Karantina)",
                        'user_id'        => Auth::id()
                    ]);
                }
                
                $totalLolosOverall += $qtyLolos;
            }

            // Update Status Receiving
            // Jika ada barang lolos -> Lanjut Putaway
            // Jika semua gagal -> Completed (Selesai di karantina)
            $receiving->status = ($totalLolosOverall > 0) ? 'PENDING_PUTAWAY' : 'COMPLETED';
            $receiving->qc_by = Auth::id();
            $receiving->qc_at = now();
            $receiving->save();

            DB::commit();
            return redirect()->route('admin.qc.index')->with('success', 'QC Selesai.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage())->withInput();
        }
    }
}