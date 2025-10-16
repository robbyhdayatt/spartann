<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryBatch;
use App\Models\Lokasi;
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
        $this->authorize('perform-warehouse-ops');
        $user = Auth::user();

        $query = Receiving::where('status', 'PENDING_QC')
                           ->with(['purchaseOrder.supplier', 'lokasi']);

        if (!$user->hasRole(['SA', 'PIC', 'MA'])) {
            $query->where('gudang_id', $user->gudang_id);
        }

        $receivings = $query->latest('tanggal_terima')->paginate(15);
        return view('admin.qc.index', compact('receivings'));
    }

    public function showQcForm(Receiving $receiving)
    {
        $this->authorize('perform-warehouse-ops');
        if ($receiving->status !== 'PENDING_QC') {
            return redirect()->route('admin.qc.index')->with('error', 'Penerimaan ini sudah diproses QC.');
        }

        if (Auth::user()->gudang_id != $receiving->gudang_id && !Auth::user()->hasRole(['SA', 'PIC'])) {
            return redirect()->route('admin.qc.index')->with('error', 'Anda tidak berwenang memproses QC untuk lokasi ini.');
        }
        
        $receiving->load(['details.part']);
        return view('admin.qc.form', compact('receiving'));
    }

    public function storeQcResult(Request $request, Receiving $receiving)
    {
        $this->authorize('perform-warehouse-ops');
        
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.qty_lolos' => 'required|integer|min:0',
            'items.*.qty_gagal' => 'required|integer|min:0',
            'items.*.catatan_qc' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $totalLolos = 0;

            foreach ($validated['items'] as $detailId => $data) {
                $detail = ReceivingDetail::findOrFail($detailId);
                $qtyLolos = (int) $data['qty_lolos'];
                $qtyGagal = (int) $data['qty_gagal'];
                $totalInput = $qtyLolos + $qtyGagal;

                if ($totalInput > $detail->qty_terima) {
                    throw new \Exception('Jumlah Lolos & Gagal QC (' . $totalInput . ') melebihi jumlah diterima (' . $detail->qty_terima . ') untuk part ' . $detail->part->nama_part);
                }

                $detail->update([
                    'qty_lolos_qc' => $qtyLolos,
                    'qty_gagal_qc' => $qtyGagal,
                    'catatan_qc' => $data['catatan_qc'],
                ]);

                // --- LOGIKA BARU UNTUK MEMINDAHKAN STOK GAGAL QC ---
                if ($qtyGagal > 0) {
                    // 1. Cari rak karantina di lokasi penerimaan
                    $quarantineRak = Rak::where('gudang_id', $receiving->gudang_id)
                                        ->where('tipe_rak', 'KARANTINA')
                                        ->first();

                    if (!$quarantineRak) {
                        throw new \Exception("Tidak ditemukan rak karantina di lokasi " . $receiving->lokasi->nama_gudang . ". Mohon setup terlebih dahulu.");
                    }

                    // 2. Buat atau tambahkan stok ke batch di rak karantina
                    $batch = InventoryBatch::firstOrCreate(
                        [
                            'part_id' => $detail->part_id,
                            'rak_id' => $quarantineRak->id,
                            'gudang_id' => $receiving->gudang_id
                        ],
                        ['quantity' => 0, 'receiving_detail_id' => $detail->id]
                    );
                    $batch->increment('quantity', $qtyGagal);
                    
                    // 3. Catat pergerakan stok (opsional tapi sangat direkomendasikan)
                    StockMovement::create([
                        'part_id' => $detail->part_id,
                        'gudang_id' => $receiving->gudang_id,
                        'rak_id' => $quarantineRak->id,
                        'jumlah' => $qtyGagal,
                        'stok_sebelum' => $batch->quantity - $qtyGagal,
                        'stok_sesudah' => $batch->quantity,
                        'referensi_type' => get_class($receiving),
                        'referensi_id' => $receiving->id,
                        'keterangan' => 'Stok masuk ke karantina dari QC Gagal (Penerimaan: ' . $receiving->nomor_penerimaan . ')',
                        'user_id' => Auth::id(),
                    ]);
                }
                // --- AKHIR LOGIKA BARU ---

                $totalLolos += $qtyLolos;
            }

            if ($totalLolos > 0) {
                $receiving->status = 'PENDING_PUTAWAY';
            } else {
                $receiving->status = 'COMPLETED';
            }

            $receiving->qc_by = Auth::id();
            $receiving->qc_at = now();
            $receiving->save();

            DB::commit();
            return redirect()->route('admin.qc.index')->with('success', 'Hasil QC berhasil disimpan. Stok gagal QC telah dipindahkan ke rak karantina.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'GAGAL DISIMPAN: ' . $e->getMessage())->withInput();
        }
    }
}