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
            return redirect()->route('admin.qc.index')->with('error', 'Penerimaan ini sudah diproses QC.');
        }

        if (Auth::user()->lokasi_id != $receiving->lokasi_id && !Auth::user()->hasRole(['SA', 'PIC'])) {
            return redirect()->route('admin.qc.index')->with('error', 'Anda tidak berwenang memproses QC untuk lokasi ini.');
        }

        $receiving->load(['details.barang']);
        return view('admin.qc.form', compact('receiving'));
    }

public function storeQcResult(Request $request, Receiving $receiving)
    {
        $this->authorize('perform-warehouse-ops');

        // Validasi dasar tipe data dan minimal 0
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.qty_lolos' => 'required|integer|min:0',
            'items.*.qty_gagal' => 'required|integer|min:0',
            'items.*.catatan_qc' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $totalLolosOverall = 0;
            $errors = [];

            foreach ($validated['items'] as $detailId => $data) {
                $detail = ReceivingDetail::with('barang')->find($detailId);

                if (!$detail || $detail->receiving_id !== $receiving->id) {
                     // Keamanan: Pastikan detail milik receiving yang benar
                    throw new \Exception("Detail item tidak valid (ID: {$detailId}).");
                }

                $qtyLolos = (int) $data['qty_lolos'];
                $qtyGagal = (int) $data['qty_gagal'];
                $totalInput = $qtyLolos + $qtyGagal;

                if ($totalInput !== $detail->qty_terima) {
                    // Kumpulkan pesan error untuk ditampilkan kembali ke form
                    $errors["items.{$detailId}.qty_lolos"] = 'Jumlah Lolos + Gagal (' . $totalInput . ') harus sama dengan Qty Diterima (' . $detail->qty_terima . ') untuk part ' . ($detail->barang->part_name ?? 'N/A');
                }

                if (!empty($errors)) {
                    continue;
                }

                $detail->update([
                    'qty_lolos_qc' => $qtyLolos,
                    'qty_gagal_qc' => $qtyGagal,
                    'catatan_qc' => $data['catatan_qc'],
                ]);

                // --- Logika Stok Gagal QC (sudah ada) ---
                if ($qtyGagal > 0) {
                    $quarantineRak = Rak::where('lokasi_id', $receiving->lokasi_id)
                                        ->where('tipe_rak', 'KARANTINA')
                                        // Opsional: Pastikan rak aktif
                                        // ->where('is_active', true)
                                        ->first();

                    if (!$quarantineRak) {
                        throw new \Exception("Tidak ditemukan rak karantina aktif di lokasi " . $receiving->lokasi->nama_lokasi . ". Mohon setup terlebih dahulu.");
                    }

                    // Cari batch yang ada atau buat baru
                    $batch = InventoryBatch::firstOrNew(
                        [
                            'barang_id' => $detail->barang_id,
                            'rak_id' => $quarantineRak->id,
                            'lokasi_id' => $receiving->lokasi_id,
                            // Opsional: Jika ingin batch terpisah per receiving
                            // 'receiving_detail_id' => $detail->id
                        ]
                    );

                    // Ambil stok sebelum untuk stock movement
                    $stokSebelumKarantina = $batch->quantity ?? 0;
                    $batch->quantity = ($batch->quantity ?? 0) + $qtyGagal;
                    // Pastikan receiving_detail_id terisi jika batch baru
                    if (!$batch->exists && $batch->receiving_detail_id === null) {
                        $batch->receiving_detail_id = $detail->id;
                    }
                    $batch->save();


                    StockMovement::create([
                        'barang_id' => $detail->barang_id,
                        'lokasi_id' => $receiving->lokasi_id,
                        'rak_id' => $quarantineRak->id,
                        'jumlah' => $qtyGagal,
                        'stok_sebelum' => $stokSebelumKarantina,
                        'stok_sesudah' => $batch->quantity,
                        'referensi_type' => get_class($receiving),
                        'referensi_id' => $receiving->id,
                        'keterangan' => 'Stok masuk ke karantina dari QC Gagal (Penerimaan: ' . $receiving->nomor_penerimaan . ')',
                        'user_id' => Auth::id(),
                    ]);
                }
                // --- Akhir Logika Stok Gagal ---

                $totalLolosOverall += $qtyLolos;
            }

            // Jika ada error validasi jumlah dari loop, throw exception
            if (!empty($errors)) {
                 // Throw ValidationException agar error ditampilkan di form
                throw ValidationException::withMessages($errors);
            }


            // Tentukan status receiving berdasarkan total lolos
            if ($totalLolosOverall > 0) {
                $receiving->status = 'PENDING_PUTAWAY';
            } else {
                 // Jika tidak ada yang lolos sama sekali (semua gagal atau semua 0)
                $receiving->status = 'COMPLETED'; // Langsung complete karena tidak ada yg perlu di-putaway
            }

            $receiving->qc_by = Auth::id();
            $receiving->qc_at = now();
            $receiving->save();

            DB::commit();
            return redirect()->route('admin.qc.index')->with('success', 'Hasil QC berhasil disimpan.' . ($totalLolosOverall == 0 ? ' Tidak ada barang lolos QC.' : ''));

        } catch (ValidationException $e) { // Tangkap ValidationException
             DB::rollBack();
             // Redirect kembali dengan error validasi
             return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            // Tampilkan error umum
            return back()->with('error', 'GAGAL DISIMPAN: ' . $e->getMessage())->withInput();
        }
    }
}
