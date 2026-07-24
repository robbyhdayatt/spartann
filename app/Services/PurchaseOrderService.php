<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Models\Lokasi;
use App\Models\Barang;
use App\Models\InventoryBatch;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class PurchaseOrderService
{
    /**
     * Memproses pembuatan PO ke Supplier.
     *
     * @param array $data Input request
     * @param User $user User pembuat PO
     * @return PurchaseOrder
     * @throws Exception
     */
    public function createSupplierPO(array $data, User $user): PurchaseOrder
    {
        $gudangId = $user->lokasi_id;

        return DB::transaction(function () use ($data, $user, $gudangId) {
            $poNumber = 'PO-SUP-' . date('ymd') . '-' . strtoupper(Str::random(4));

            $po = PurchaseOrder::create([
                'nomor_po'    => $poNumber,
                'po_type'     => 'supplier_po',
                'tanggal_po'  => $data['tanggal_po'],
                'supplier_id' => $data['supplier_id'],
                'lokasi_id'   => $gudangId,
                'status'      => 'PENDING_APPROVAL',
                'created_by'  => $user->id,
                'catatan'     => $data['catatan'] ?? null,
            ]);

            $totalAmount = 0;

            foreach ($data['items'] as $item) {
                if (empty($item['barang_id']) || empty($item['qty'])) {
                    continue;
                }

                $barang = Barang::find($item['barang_id']);

                if (!$barang) {
                    throw new Exception("Barang tidak ditemukan.");
                }

                if (!$barang->is_active) {
                    throw new Exception("Gagal Membuat PO! Barang '{$barang->part_name}' statusnya NONAKTIF.");
                }

                $hargaBeli = $barang->selling_in > 0 ? $barang->selling_in : 0;
                $subtotal  = $item['qty'] * $hargaBeli;

                PurchaseOrderDetail::create([
                    'purchase_order_id' => $po->id,
                    'barang_id'         => $item['barang_id'],
                    'qty_pesan'         => $item['qty'],
                    'harga_beli'        => $hargaBeli,
                    'subtotal'          => $subtotal,
                ]);

                $totalAmount += $subtotal;
            }

            $po->update(['total_amount' => $totalAmount]);

            return $po;
        });
    }

    /**
     * Memproses pembuatan Dealer Request PO.
     *
     * @param array $data Input request
     * @param User $user User pembuat request
     * @return int Jumlah request yang berhasil dibuat
     * @throws Exception
     */
    public function createDealerRequest(array $data, User $user): int
    {
        $dealerRequests = json_decode($data['requests'], true);

        if (empty($dealerRequests)) {
            throw new Exception('Item request kosong.');
        }

        return DB::transaction(function () use ($data, $dealerRequests, $user) {
            $groupId = 'REQ-' . now()->timestamp;
            $count   = 0;

            foreach ($dealerRequests as $req) {
                $items = $req['items'] ?? [];
                if (empty($items)) {
                    continue;
                }

                $lokasiTujuanId = $req['lokasi_id'];
                if ($user->isDealer() && $lokasiTujuanId != $user->lokasi_id) {
                    throw new Exception("Anda hanya boleh membuat request untuk dealer Anda sendiri.");
                }

                $subtotalPO = 0;

                $po = PurchaseOrder::create([
                    'nomor_po'         => $this->generatePoNumber($lokasiTujuanId),
                    'po_type'          => 'dealer_request',
                    'request_group_id' => $groupId,
                    'tanggal_po'       => $data['tanggal_po'],
                    'sumber_lokasi_id' => $data['sumber_lokasi_id'],
                    'lokasi_id'        => $lokasiTujuanId,
                    'status'           => 'PENDING_APPROVAL',
                    'created_by'       => $user->id,
                    'total_amount'     => 0,
                ]);

                foreach ($items as $item) {
                    $barang = Barang::find($item['barang_id']);

                    if (!$barang) {
                        throw new Exception("Barang tidak ditemukan.");
                    }

                    if (!$barang->is_active) {
                        throw new Exception("Gagal Request! Barang '{$barang->part_name}' statusnya NONAKTIF.");
                    }

                    $harga    = $barang->selling_out > 0 ? $barang->selling_out : 0;
                    $subtotal = $item['qty'] * $harga;

                    $po->details()->create([
                        'barang_id'  => $barang->id,
                        'qty_pesan'  => $item['qty'],
                        'harga_beli' => $harga,
                        'subtotal'   => $subtotal,
                    ]);
                    $subtotalPO += $subtotal;
                }

                $po->update(['total_amount' => $subtotalPO]);
                $count++;
            }

            return $count;
        });
    }

    /**
     * Memproses approval Purchase Order.
     *
     * @param PurchaseOrder $purchaseOrder PO yang akan diapprove
     * @param array $qtyApproved Input qty yang disetujui (opsional)
     * @param User $approver User yang menyetujui
     * @return void
     * @throws Exception
     */
    public function approvePO(PurchaseOrder $purchaseOrder, array $qtyApproved, User $approver): void
    {
        if ($purchaseOrder->status !== 'PENDING_APPROVAL') {
            throw new Exception('PO sudah diproses.');
        }

        DB::transaction(function () use ($purchaseOrder, $qtyApproved, $approver) {
            if ($purchaseOrder->po_type === 'supplier_po') {
                $purchaseOrder->update([
                    'status'      => 'APPROVED',
                    'approved_by' => $approver->id,
                    'approved_at' => now(),
                ]);
            } elseif ($purchaseOrder->po_type === 'dealer_request') {
                $poLocked = PurchaseOrder::where('id', $purchaseOrder->id)->lockForUpdate()->first();

                foreach ($poLocked->details as $detail) {
                    $qtyApprove = isset($qtyApproved[$detail->id]) ? (int) $qtyApproved[$detail->id] : $detail->qty_pesan;

                    $detail->update(['qty_disetujui' => $qtyApprove]);

                    if ($qtyApprove > 0) {
                        $batches = InventoryBatch::where('lokasi_id', $poLocked->sumber_lokasi_id)
                            ->where('barang_id', $detail->barang_id)
                            ->where('quantity', '>', 0)
                            ->orderBy('created_at', 'asc')
                            ->lockForUpdate()
                            ->get();

                        $sisaButuh = $qtyApprove;

                        if ($batches->sum('quantity') < $sisaButuh) {
                            throw new Exception("Stok {$detail->barang->part_name} di Gudang Sumber tidak cukup.");
                        }

                        foreach ($batches as $batch) {
                            if ($sisaButuh <= 0) {
                                break;
                            }
                            $ambil = min($batch->quantity, $sisaButuh);

                            $stokAwal = $batch->quantity;
                            $batch->decrement('quantity', $ambil);

                            StockMovement::create([
                                'barang_id'      => $detail->barang_id,
                                'lokasi_id'      => $poLocked->sumber_lokasi_id,
                                'rak_id'         => $batch->rak_id,
                                'jumlah'         => -$ambil,
                                'stok_sebelum'   => $stokAwal,
                                'stok_sesudah'   => $stokAwal - $ambil,
                                'referensi_type' => PurchaseOrder::class,
                                'referensi_id'   => $poLocked->id,
                                'keterangan'     => 'Transfer Out ke ' . $poLocked->lokasi->nama_lokasi,
                                'user_id'        => $approver->id,
                                'created_at'     => now(),
                            ]);

                            $sisaButuh -= $ambil;
                        }
                    }
                }

                $poLocked->update([
                    'status'      => 'APPROVED',
                    'approved_by' => $approver->id,
                    'approved_at' => now(),
                ]);
            }
        });
    }

    private function generatePoNumber($lokasiId): string
    {
        $lokasi = Lokasi::find($lokasiId);
        $kode   = $lokasi ? $lokasi->kode_lokasi : 'GEN';
        $date   = now()->format('ymd');
        $seq    = PurchaseOrder::whereDate('created_at', today())->count() + 1;
        return "PO/{$kode}/{$date}/" . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }
}
