<?php

namespace App\Services;

use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Models\Konsumen;
use App\Models\Lokasi;
use App\Models\Barang;
use App\Models\InventoryBatch;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class PenjualanService
{
    /**
     * Memproses dan menyimpan transaksi penjualan (POS) baru.
     *
     * @param array $data Input request dari controller
     * @param User $user User yang melakukan transaksi
     * @return Penjualan
     * @throws Exception
     */
    public function createPenjualan(array $data, User $user): Penjualan
    {
        $lokasiId = $user->lokasi_id;

        if (!$lokasiId && $user->isGlobal()) {
            $lokasiId = Lokasi::where('tipe', 'DEALER')->first()->id ?? null;
        }

        if (!$lokasiId) {
            throw new Exception('Lokasi penjualan tidak valid.');
        }

        return DB::transaction(function () use ($data, $user, $lokasiId) {
            // 1. Handle Data Konsumen (Cari atau Buat Baru)
            $konsumen = Konsumen::firstOrCreate(
                ['nama_konsumen' => $data['customer_name']],
                [
                    'kode_konsumen' => 'CST-' . now()->format('ymd-His'),
                    'tipe_konsumen' => $data['tipe_konsumen'],
                    'alamat'        => $data['alamat'] ?? '-',
                    'telepon'       => $data['telepon'] ?? '-',
                    'is_active'     => true,
                ]
            );

            if (!$konsumen->wasRecentlyCreated) {
                $konsumen->update([
                    'tipe_konsumen' => $data['tipe_konsumen'],
                    'alamat'        => $data['alamat'] ?? $konsumen->alamat,
                    'telepon'       => $data['telepon'] ?? $konsumen->telepon,
                ]);
            }

            // 2. Buat Header Penjualan
            $penjualan = Penjualan::create([
                'nomor_faktur'      => Penjualan::generateNomorFaktur($lokasiId),
                'tanggal_jual'      => $data['tanggal_jual'],
                'lokasi_id'         => $lokasiId,
                'konsumen_id'       => $konsumen->id,
                'sales_id'          => $user->id,
                'created_by'        => $user->id,
                'status'            => 'COMPLETED',
                'keterangan_diskon' => $data['nama_diskon'] ?? null,
                'diskon'            => 0,
                'subtotal'          => 0,
                'pajak'             => 0,
                'total_harga'       => 0,
            ]);

            $subtotalGlobal = 0;

            // 3. Proses Setiap Item
            foreach ($data['items'] as $item) {
                $barangId   = $item['barang_id'];
                $qtyRequest = (int) $item['qty'];

                $barang = Barang::find($barangId);

                if (!$barang) {
                    throw new Exception("Barang tidak ditemukan.");
                }

                if (!$barang->is_active) {
                    throw new Exception("Transaksi Dibatalkan! Barang '{$barang->part_name}' ({$barang->part_code}) berstatus NONAKTIF dan tidak dapat dijual.");
                }

                $hargaJualSatuan  = $barang->retail;
                $hargaModalSatuan = $barang->selling_out ?? 0;

                // Ambil stok dari inventory_batch (FIFO)
                $batches = InventoryBatch::where('barang_id', $barangId)
                    ->where('lokasi_id', $lokasiId)
                    ->where('quantity', '>', 0)
                    ->orderBy('created_at', 'asc')
                    ->lockForUpdate()
                    ->get();

                $totalStokTersedia = $batches->sum('quantity');

                if ($totalStokTersedia < $qtyRequest) {
                    throw new Exception("Stok tidak mencukupi untuk barang: {$barang->part_name}. Diminta: {$qtyRequest}, Tersedia: {$totalStokTersedia}");
                }

                $sisaQtyYangHarusDipenuhi = $qtyRequest;

                // Loop batches untuk mengurangi stok (Split Rak/Batch)
                foreach ($batches as $batch) {
                    if ($sisaQtyYangHarusDipenuhi <= 0) {
                        break;
                    }

                    $qtyDiambil = min($batch->quantity, $sisaQtyYangHarusDipenuhi);
                    $subtotalItem = $qtyDiambil * $hargaJualSatuan;

                    // Detail Penjualan
                    $penjualan->details()->create([
                        'barang_id'   => $barang->id,
                        'rak_id'      => $batch->rak_id,
                        'qty_jual'    => $qtyDiambil,
                        'harga_jual'  => $hargaJualSatuan,
                        'harga_modal' => $hargaModalSatuan,
                        'subtotal'    => $subtotalItem,
                        'qty_diretur' => 0,
                    ]);

                    // Update Inventory Batch
                    $stokAwalBatch = $batch->quantity;
                    $batch->decrement('quantity', $qtyDiambil);

                    // Catat Kartu Stok (Movement)
                    StockMovement::create([
                        'barang_id'      => $barang->id,
                        'lokasi_id'      => $lokasiId,
                        'rak_id'         => $batch->rak_id,
                        'jumlah'         => -$qtyDiambil,
                        'stok_sebelum'   => $stokAwalBatch,
                        'stok_sesudah'   => $stokAwalBatch - $qtyDiambil,
                        'referensi_type' => get_class($penjualan),
                        'referensi_id'   => $penjualan->id,
                        'keterangan'     => "Penjualan POS #{$penjualan->nomor_faktur}",
                        'user_id'        => $user->id,
                    ]);

                    $sisaQtyYangHarusDipenuhi -= $qtyDiambil;
                    $subtotalGlobal += $subtotalItem;
                }
            }

            // 4. Kalkulasi Final (Diskon & Pajak)
            $inputDiskon = (float) ($data['nilai_diskon'] ?? 0);
            $finalDiskon = min($inputDiskon, $subtotalGlobal);

            $dpp = $subtotalGlobal - $finalDiskon;

            $nilaiPajak = 0;
            if (isset($data['ppn_check']) && $data['ppn_check'] == 1) {
                $nilaiPajak = $dpp * 0.11; // PPN 11%
            }

            $grandTotal = $dpp + $nilaiPajak;

            // 5. Update Header Penjualan
            $penjualan->update([
                'subtotal'     => $subtotalGlobal,
                'diskon'       => $finalDiskon,
                'total_diskon' => $finalDiskon,
                'pajak'        => $nilaiPajak,
                'total_harga'  => $grandTotal,
            ]);

            return $penjualan;
        });
    }
}
