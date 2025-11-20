<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Rak;
use App\Models\Lokasi;
use Illuminate\Support\Facades\Schema;

class RakSeeder extends Seeder
{
    public function run()
    {
        // Bersihkan data lama agar tidak duplikat saat seeding ulang
        Schema::disableForeignKeyConstraints();
        Rak::truncate();
        Schema::enableForeignKeyConstraints();

        // Ambil data lokasi
        $pusat = Lokasi::where('tipe', 'PUSAT')->first();
        $dealers = Lokasi::where('tipe', 'DEALER')->get();

        // 1. Buat Rak untuk GUDANG PUSAT (Lebih banyak)
        if ($pusat) {
            // Buat 10 Rak Penyimpanan untuk Pusat
            $this->createStorageRaks($pusat, 10);

            // Rak Karantina Pusat
            Rak::create([
                'lokasi_id' => $pusat->id,
                'kode_rak'  => 'KARANTINA-PST',
                'nama_rak'  => 'Area Karantina Pusat',
                'tipe_rak'  => 'KARANTINA',
                'is_active' => true,
            ]);
        }

        // 2. Buat Rak untuk SETIAP DEALER
        foreach ($dealers as $dealer) {
            // ++ SESUAI REQUEST: Tambahkan 3 Rak Penyimpanan ++
            $this->createStorageRaks($dealer, 3);

            // Tambahkan 1 Rak Display (Untuk pajangan toko)
            Rak::create([
                'lokasi_id' => $dealer->id,
                'kode_rak'  => 'DISPLAY-01',
                'nama_rak'  => 'Rak Display Toko',
                'tipe_rak'  => 'DISPLAY',
                'is_active' => true,
            ]);

            // Tambahkan 1 Rak Karantina (Wajib ada untuk retur/barang rusak)
            Rak::create([
                'lokasi_id' => $dealer->id,
                'kode_rak'  => 'KARANTINA-01',
                'nama_rak'  => 'Rak Barang Rusak/Retur',
                'tipe_rak'  => 'KARANTINA',
                'is_active' => true,
            ]);
        }
    }

    /**
     * Helper untuk membuat rak penyimpanan dalam jumlah banyak
     */
    private function createStorageRaks($lokasi, $jumlah)
    {
        for ($i = 1; $i <= $jumlah; $i++) {
            // Format kode: RAK-01, RAK-02, dst.
            $nomor = str_pad($i, 2, '0', STR_PAD_LEFT);

            Rak::create([
                'lokasi_id' => $lokasi->id,
                'kode_rak'  => 'RAK-' . $nomor,
                'nama_rak'  => 'Rak Penyimpanan ' . $i,
                'tipe_rak'  => 'PENYIMPANAN',
                'is_active' => true,
            ]);
        }
    }
}
