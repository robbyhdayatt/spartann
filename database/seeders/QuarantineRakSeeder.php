<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\Rak;
use App\Models\Lokasi;

class QuarantineRakSeeder extends Seeder
{
    public function run()
    {
        $lokasi = Lokasi::all();
        foreach ($lokasi as $Lokasi) {
            Rak::firstOrCreate(
                ['kode_rak' => $Lokasi->kode_Lokasi . '-KRN-RT'],
                [
                    'Lokasi_id' => $Lokasi->id,
                    'nama_rak' => 'Rak Karantina Retur',
                    'tipe_rak' => 'KARANTINA', // <-- TAMBAHKAN BARIS INI
                    'is_active' => true
                ]
            );
        }
    }
}
