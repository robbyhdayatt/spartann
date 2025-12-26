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
        Schema::disableForeignKeyConstraints();
        Rak::truncate();
        Schema::enableForeignKeyConstraints();

        // Ambil Gudang Pusat
        $pusat = Lokasi::where('tipe', 'PUSAT')->first();
        // Ambil Dealer
        $dealers = Lokasi::where('tipe', 'DEALER')->get();

        // 1. SEED RAK GUDANG PUSAT (Format Lengkap)
        if ($pusat) {
            // Buat Zona A, Rak 01-03, Level 1-3, Bin 01-05
            $this->generateRaks($pusat, 'A', 3, 3, 5, 'PENYIMPANAN');
            
            // Rak Karantina Pusat (Simpel)
            Rak::create([
                'lokasi_id' => $pusat->id,
                'zona'      => 'K',   // K = Karantina
                'nomor_rak' => '00',
                'level'     => '00',
                'bin'       => '00',
                'tipe_rak'  => 'KARANTINA',
                'is_active' => true,
            ]);
        }

        // 2. SEED RAK DEALER
        foreach ($dealers as $dealer) {
            // Dealer biasanya lebih kecil: Zona D (Display), Zona S (Stock)
            
            // Rak Stock: Zona S, 1 Rak, 2 Level, 3 Bin
            $this->generateRaks($dealer, 'S', 1, 2, 3, 'PENYIMPANAN');

            // Rak Display
            Rak::create([
                'lokasi_id' => $dealer->id,
                'zona'      => 'D', // Display
                'nomor_rak' => '01',
                'level'     => '01',
                'bin'       => '01',
                'tipe_rak'  => 'DISPLAY', // Pastikan enum di database mendukung atau pakai PENYIMPANAN
                'is_active' => true,
            ]);

            // Rak Karantina Dealer
            Rak::create([
                'lokasi_id' => $dealer->id,
                'zona'      => 'K',
                'nomor_rak' => '00',
                'level'     => '00',
                'bin'       => '01',
                'tipe_rak'  => 'KARANTINA',
                'is_active' => true,
            ]);
        }
    }

    private function generateRaks($lokasi, $zona, $maxRak, $maxLevel, $maxBin, $tipe)
    {
        for ($r = 1; $r <= $maxRak; $r++) {
            for ($l = 1; $l <= $maxLevel; $l++) {
                for ($b = 1; $b <= $maxBin; $b++) {
                    
                    Rak::create([
                        'lokasi_id' => $lokasi->id,
                        'zona'      => $zona,
                        'nomor_rak' => 'R' . str_pad($r, 2, '0', STR_PAD_LEFT), // R01
                        'level'     => 'L' . $l, // L1
                        'bin'       => 'B' . str_pad($b, 2, '0', STR_PAD_LEFT), // B01
                        'tipe_rak'  => $tipe,
                        'is_active' => true,
                        // kode_rak & nama_rak akan digenerate otomatis oleh Model Boot() yang saya buat sebelumnya
                    ]);

                }
            }
        }
    }
}