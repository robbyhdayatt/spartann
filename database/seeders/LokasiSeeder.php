<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Lokasi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LokasiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Schema::disableForeignKeyConstraints();
        DB::table('lokasi')->truncate();
        Schema::enableForeignKeyConstraints();

        // 1. Buat Gudang Pusat
        Lokasi::create([
            'tipe' => 'PUSAT',
            'kode_gudang' => 'GSP',
            'nama_gudang' => 'Gudang Pusat (Sentral)',
            'alamat' => 'Jl. Ikan Tenggiri No. 24, Bandar Lampung',
            'is_active' => 1,
        ]);

        // 2. Data Dealer dari sumber yang Anda berikan
        $dealers = [
            ['kode_lks' => '9HL001', 'subyek' => 'LTI TANJUNG BINTANG', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => '9HL002', 'subyek' => 'LTI LIWA', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => '9HL002A', 'subyek' => 'LTI KRUI', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => '9HL002B', 'subyek' => 'LTI SUMBER JAYA', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => '9HL003', 'subyek' => 'LTI KEDATON', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => '9HL003B', 'subyek' => 'LTI PRAMUKA', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => '9HL004', 'subyek' => 'LTI PURBOLINGGO', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => '9HL004A', 'subyek' => 'LTI SEKAMPUNG', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => '9HL006', 'subyek' => 'LTI MANDALA', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => '9HL007', 'subyek' => 'LTI TIRTAYASA', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => '9HL007A', 'subyek' => 'LTI BINA KARYA', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => '9HL008', 'subyek' => 'LTI KOTA AGUNG', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => '9HL009', 'subyek' => 'LTI SIMPANG PEMATANG', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => 'UA22001A', 'subyek' => 'LTI KEMILING', 'grup' => 'LT', 'nonaktif' => 1],
            ['kode_lks' => 'UA22001', 'subyek' => 'LTI SENTRAL YAMAHA', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => 'UA22002', 'subyek' => 'LTI PAHOMAN', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => 'UA22003', 'subyek' => 'LTI KARANG ANYAR', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => 'UA22003A', 'subyek' => 'LTI NATAR', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => 'UB22001', 'subyek' => 'LTI METRO', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => 'UB22001A', 'subyek' => 'LTI PEKALONGAN', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => 'UB22001B', 'subyek' => 'LTI IMOPURO', 'grup' => 'LT', 'nonaktif' => 1],
            ['kode_lks' => 'UC22001', 'subyek' => 'LTI KALIANDA', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => 'UC22001A', 'subyek' => 'LTI PATOK', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => 'UC22002', 'subyek' => 'LTI PEMATANG PASIR', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => 'UCUB001', 'subyek' => 'LTI GEDUNG TATAAN', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => 'UCUB001A', 'subyek' => 'LTI WIYONO', 'grup' => 'LT', 'nonaktif' => 1],
            ['kode_lks' => 'UD00003', 'subyek' => 'LTI RAWAJITU', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => 'UDUA001', 'subyek' => 'LTI UNIT DUA', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => 'UDUA001A', 'subyek' => 'LTI GUNUNG TERANG', 'grup' => 'LT', 'nonaktif' => 1],
            ['kode_lks' => 'UDUA002', 'subyek' => 'LTI MENGGALA', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => 'UDUA002A', 'subyek' => 'LTI DAYA MURNI', 'grup' => 'LT', 'nonaktif' => 1],
            ['kode_lks' => 'UDUB001A', 'subyek' => 'LTI BRABASAN', 'grup' => 'LT', 'nonaktif' => 1],
            ['kode_lks' => 'UDUB002', 'subyek' => 'LTI BANJAR AGUNG', 'grup' => 'LT', 'nonaktif' => 1],
            ['kode_lks' => 'UE22001', 'subyek' => 'LTI BANDAR JAYA', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => 'UE22002', 'subyek' => 'LTI KOTA GAJAH', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => 'UE22002A', 'subyek' => 'LTI PUNGGUR', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => 'UE22003', 'subyek' => 'LTI RUMBIA', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => 'UE22003A', 'subyek' => 'LTI GAYA BARU', 'grup' => 'LT', 'nonaktif' => 1],
            ['kode_lks' => 'UFUA001', 'subyek' => 'LTI KOTABUMI', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => 'UFUA001B', 'subyek' => 'LTI BUNGA MAYANG', 'grup' => 'LT', 'nonaktif' => 1],
            ['kode_lks' => 'UFUA001C', 'subyek' => 'LTI PAKUAN RATU', 'grup' => 'LT', 'nonaktif' => 1],
            ['kode_lks' => 'UHUB001', 'subyek' => 'LTI SRIBHAWONO', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => 'UHUB001A', 'subyek' => 'LTI TRIDATU', 'grup' => 'LT', 'nonaktif' => 1],
            ['kode_lks' => 'UHUE002', 'subyek' => 'LTI MARGATIGA', 'grup' => 'LT', 'nonaktif' => 1],
            ['kode_lks' => 'UIUB001', 'subyek' => 'LTI PRINGSEWU', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => 'UIUB002', 'subyek' => 'LTI KALIREJO', 'grup' => 'LT', 'nonaktif' => 0],
            ['kode_lks' => 'UKUA001', 'subyek' => 'LTI BLAMBANGAN UMPU', 'grup' => 'LT', 'nonaktif' => 0],
        ];
        
        // 3. Masukkan setiap dealer sebagai Lokasi Tipe DEALER
        foreach ($dealers as $dealer) {
            if (strtoupper($dealer['grup']) === 'LT') {
                Lokasi::create([
                    'tipe' => 'DEALER',
                    'kode_gudang' => $dealer['kode_lks'],
                    'nama_gudang' => $dealer['subyek'],
                    'is_active' => $dealer['nonaktif'] == 0,
                ]);
            }
        }
    }
}