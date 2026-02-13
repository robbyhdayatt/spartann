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

        // Ambil Gudang Pusat berdasarkan kode baru
        $pusat = Lokasi::where('kode_lokasi', 'GUDANG PART')->first();
        
        // Ambil Dealer
        $dealers = Lokasi::where('tipe', 'DEALER')->get();

        // 1. SEED RAK GUDANG PUSAT (GUDANG PART)
        if ($pusat) {
            // Buat Zona A, Rak 01-03, Level 1-3, Bin 01-05
            $this->generateRaks($pusat, 'A', 3, 3, 5, 'PENYIMPANAN');
            
            // Rak Karantina Pusat
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
            // Rak Stock: Zona S, 1 Rak, 2 Level, 3 Bin
            $this->generateRaks($dealer, 'S', 1, 2, 3, 'PENYIMPANAN');

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
                        'nomor_rak' => 'R' . str_pad($r, 2, '0', STR_PAD_LEFT),
                        'level'     => 'L' . $l,
                        'bin'       => 'B' . str_pad($b, 2, '0', STR_PAD_LEFT),
                        'tipe_rak'  => $tipe,
                        'is_active' => true,
                    ]);

                }
            }
        }
    }
}