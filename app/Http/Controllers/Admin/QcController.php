<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Receiving;
use App\Models\ReceivingDetail;
use App\Models\Lokasi; // DIUBAH
use App\Models\Rak;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class QcController extends Controller
{
    public function index()
    {
        // PERUBAHAN: Menggunakan gate 'perform-warehouse-ops'
        $this->authorize('perform-warehouse-ops');
        $user = Auth::user();

        $query = Receiving::where('status', 'PENDING_QC')
                            // PERUBAHAN: Menggunakan relasi 'lokasi'
                            ->with(['purchaseOrder.supplier', 'lokasi']);

        // Filter berdasarkan lokasi user, kecuali untuk role global
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

        // Pastikan user hanya bisa proses QC di lokasinya
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
            return redirect()->route('admin.qc.index')->with('success', 'Hasil QC berhasil disimpan.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'GAGAL DISIMPAN: ' . $e->getMessage())->withInput();
        }
    }
}