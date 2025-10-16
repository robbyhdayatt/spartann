<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Lokasi; // Model yang benar untuk lokasi
use App\Models\Rak;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RakSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Schema::disableForeignKeyConstraints();
        Rak::query()->truncate(); // Menggunakan model untuk truncate
        Schema::enableForeignKeyConstraints();

        // Ambil semua lokasi yang aktif untuk dibuatkan rak
        $allLocations = Lokasi::where('is_active', true)->get();

        foreach ($allLocations as $lokasi) {
            if ($lokasi->tipe === 'PUSAT') {
                // Buat rak standar untuk Gudang Pusat
                Rak::firstOrCreate(
                    ['gudang_id' => $lokasi->id, 'kode_rak' => 'A-01-01'],
                    ['nama_rak' => 'Rak Penyimpanan A-01-01', 'tipe_rak' => 'PENYIMPANAN']
                );
                Rak::firstOrCreate(
                    ['gudang_id' => $lokasi->id, 'kode_rak' => 'A-01-02'],
                    ['nama_rak' => 'Rak Penyimpanan A-01-02', 'tipe_rak' => 'PENYIMPANAN']
                );
                Rak::firstOrCreate(
                    ['gudang_id' => $lokasi->id, 'kode_rak' => 'KRN-QC'],
                    ['nama_rak' => 'Rak Karantina QC', 'tipe_rak' => 'KARANTINA']
                );
                Rak::firstOrCreate(
                    ['gudang_id' => $lokasi->id, 'kode_rak' => 'KRN-RT'],
                    ['nama_rak' => 'Rak Karantina Retur', 'tipe_rak' => 'KARANTINA']
                );
            }
            elseif ($lokasi->tipe === 'DEALER') {
                // Buat rak standar untuk setiap Dealer
                Rak::firstOrCreate(
                    ['gudang_id' => $lokasi->id, 'kode_rak' => $lokasi->kode_gudang . '-01'],
                    ['nama_rak' => 'Rak Penyimpanan Utama', 'tipe_rak' => 'PENYIMPANAN']
                );
                Rak::firstOrCreate(
                    ['gudang_id' => $lokasi->id, 'kode_rak' => $lokasi->kode_gudang . '-KRN'],
                    ['nama_rak' => 'Rak Karantina Dealer', 'tipe_rak' => 'KARANTINA']
                );
            }
        }
    }
}
