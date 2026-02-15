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
use Illuminate\Validation\ValidationException;

class QcController extends Controller
{
    public function index()
    {
        $this->authorize('perform-warehouse-ops');
        $user = Auth::user();

        $query = Receiving::where('status', 'PENDING_QC')
            ->with(['purchaseOrder.supplier', 'lokasi']);

        if (!$user->hasRole(['SA', 'PIC', 'MA'])) {
            $query->where('lokasi_id', $user->lokasi_id);
        }

        $receivings = $query->latest('tanggal_terima')->paginate(15);
        return view('admin.qc.index', compact('receivings'));
    }

    public function showQcForm(Receiving $receiving)
    {
        $this->authorize('perform-warehouse-ops');
        
        if ($receiving->status !== 'PENDING_QC') {
            return redirect()->route('admin.qc.index')->with('error', 'Dokumen ini tidak dalam status PENDING QC.');
        }

        if (Auth::user()->lokasi_id && Auth::user()->lokasi_id != $receiving->lokasi_id) {
            return redirect()->route('admin.qc.index')->with('error', 'Akses Ditolak: Lokasi berbeda.');
        }

        $receiving->load(['details.barang']);
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
            // Lock Receiving untuk mencegah double process
            $receiving = Receiving::where('id', $receiving->id)->lockForUpdate()->first();

            if ($receiving->status !== 'PENDING_QC') {
                throw new \Exception("Status dokumen telah berubah. Mohon refresh halaman.");
            }

            $totalLolosOverall = 0;
            $errors = [];

            // Pre-check Rak Karantina (Hanya jika ada barang gagal)
            $hasFailure = collect($validated['items'])->sum('qty_gagal') > 0;
            $quarantineRak = null;
            
            if ($hasFailure) {
                $quarantineRak = Rak::where('lokasi_id', $receiving->lokasi_id)
                    ->where('tipe_rak', 'KARANTINA')
                    ->first();

                if (!$quarantineRak) {
                    throw new \Exception("Rak Karantina belum disetting di lokasi ini. Hubungi Admin untuk membuatnya.");
                }
            }

            foreach ($validated['items'] as $detailId => $data) {
                $detail = ReceivingDetail::with('barang')->find($detailId);

                if (!$detail || $detail->receiving_id !== $receiving->id) {
                    throw new \Exception("Item Detail ID {$detailId} tidak valid.");
                }

                $qtyLolos = (int) $data['qty_lolos'];
                $qtyGagal = (int) $data['qty_gagal'];
                $totalInput = $qtyLolos + $qtyGagal;

                // 1. Validasi Jumlah
                if ($totalInput !== $detail->qty_terima) {
                    $errors["items.{$detailId}.qty_lolos"] = "Total input ({$totalInput}) tidak sama dengan Qty Diterima ({$detail->qty_terima}) pada barang {$detail->barang->part_name}.";
                    continue; // Skip ke item berikutnya, nanti throw error bulk
                }

                // 2. Update Receiving Detail
                $detail->update([
                    'qty_lolos_qc' => $qtyLolos,
                    'qty_gagal_qc' => $qtyGagal,
                    'catatan_qc' => $data['catatan_qc'],
                ]);

                // 3. Handle Barang Gagal -> Masuk Rak Karantina
                if ($qtyGagal > 0) {
                    // Cari/Buat Batch di Rak Karantina
                    $batch = InventoryBatch::firstOrNew([
                        'barang_id' => $detail->barang_id,
                        'rak_id'    => $quarantineRak->id,
                        'lokasi_id' => $receiving->lokasi_id,
                        // Kita bisa pisahkan batch per PO jika mau, tapi untuk karantina biasanya digabung
                        // 'receiving_detail_id' => $detail->id 
                    ]);
                    
                    $stokAwal = $batch->quantity ?? 0;
                    $batch->quantity = $stokAwal + $qtyGagal;
                    $batch->save();

                    // Catat Mutasi Masuk Karantina
                    StockMovement::create([
                        'barang_id' => $detail->barang_id,
                        'lokasi_id' => $receiving->lokasi_id,
                        'rak_id'    => $quarantineRak->id,
                        'jumlah'    => $qtyGagal,
                        'stok_sebelum' => $stokAwal,
                        'stok_sesudah' => $batch->quantity,
                        'referensi_type' => Receiving::class,
                        'referensi_id'   => $receiving->id,
                        'keterangan'     => "QC Gagal (Masuk Karantina)",
                        'user_id'        => Auth::id()
                    ]);
                }

                $totalLolosOverall += $qtyLolos;
            }

            if (!empty($errors)) {
                throw ValidationException::withMessages($errors);
            }

            // 4. Update Status Receiving
            if ($totalLolosOverall > 0) {
                $receiving->status = 'PENDING_PUTAWAY';
            } else {
                // Jika semua barang gagal, maka receiving dianggap selesai (tidak ada yang perlu disimpan di rak sales)
                $receiving->status = 'COMPLETED';
                
                // Juga update status PO jika ini langkah terakhir
                if ($receiving->purchaseOrder) {
                    $receiving->purchaseOrder->syncStatus();
                }
            }

            $receiving->qc_by = Auth::id();
            $receiving->qc_at = now();
            $receiving->save();

            DB::commit();

            $msg = ($totalLolosOverall > 0) 
                ? 'QC Selesai. Silakan lanjut ke proses Putaway.' 
                : 'QC Selesai. Semua barang masuk karantina (Tidak ada Putaway).';

            return redirect()->route('admin.qc.index')->with('success', $msg);

        } catch (ValidationException $e) {
            DB::rollBack();
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error: ' . $e->getMessage())->withInput();
        }
    }
}