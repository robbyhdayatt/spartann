<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Dealer;
use Illuminate\Support\Facades\DB;

class DealerSeeder extends Seeder
{
    public function run()
    {
        // Mengosongkan tabel dealers sebelum diisi
        DB::table('dealers')->truncate();

        $dealersData = [
            ['kode_lks' => '9HL001', 'subyek' => 'LTI TANJUNG BINTANG', 'grup' => 'LT', 'singkatan' => 'TJB', 'kota' => 'Tanjung Bintang', 'nonaktif' => 0],
            ['kode_lks' => '9HL002', 'subyek' => 'LTI LIWA', 'grup' => 'LT', 'singkatan' => 'LWA', 'kota' => 'Liwa', 'nonaktif' => 0],
            ['kode_lks' => '9HL002A', 'subyek' => 'LTI KRUI', 'grup' => 'LT', 'singkatan' => 'KRI', 'kota' => 'Krui', 'nonaktif' => 0],
            ['kode_lks' => '9HL002B', 'subyek' => 'LTI SUMBER JAYA', 'grup' => 'LT', 'singkatan' => 'SBJ', 'kota' => 'Sumber Jaya', 'nonaktif' => 0],
            ['kode_lks' => '9HL003', 'subyek' => 'LTI KEDATON', 'grup' => 'LT', 'singkatan' => 'KDT', 'kota' => 'Kedaton', 'nonaktif' => 0],
            ['kode_lks' => '9HL003B', 'subyek' => 'LTI PRAMUKA', 'grup' => 'LT', 'singkatan' => 'PMK', 'kota' => 'Pramuka', 'nonaktif' => 0],
            ['kode_lks' => '9HL004', 'subyek' => 'LTI PURBOLINGGO', 'grup' => 'LT', 'singkatan' => 'PBG', 'kota' => 'Purbolinggo', 'nonaktif' => 0],
            ['kode_lks' => '9HL004A', 'subyek' => 'LTI SEKAMPUNG', 'grup' => 'LT', 'singkatan' => 'SKP', 'kota' => 'Sekampung', 'nonaktif' => 0],
            ['kode_lks' => '9HL006', 'subyek' => 'LTI MANDALA', 'grup' => 'LT', 'singkatan' => 'MDL', 'kota' => 'Mandala', 'nonaktif' => 0],
            ['kode_lks' => '9HL007', 'subyek' => 'LTI TIRTAYASA', 'grup' => 'LT', 'singkatan' => 'TTY', 'kota' => 'Tirtayasa', 'nonaktif' => 0],
            ['kode_lks' => '9HL007A', 'subyek' => 'LTI BINA KARYA', 'grup' => 'LT', 'singkatan' => 'BLK', 'kota' => 'Bina Karya', 'nonaktif' => 0],
            ['kode_lks' => '9HL008', 'subyek' => 'LTI KOTA AGUNG', 'grup' => 'LT', 'singkatan' => 'KTA', 'kota' => 'Kota Agung', 'nonaktif' => 0],
            ['kode_lks' => '9HL009', 'subyek' => 'LTI SIMPANG PEMATANG', 'grup' => 'LT', 'singkatan' => 'SPM', 'kota' => 'Simpang Pematang', 'nonaktif' => 0],
            ['kode_lks' => 'UA22001A', 'subyek' => 'LTI KEMILING', 'grup' => 'LT', 'singkatan' => 'KML', 'kota' => 'Kemiling', 'nonaktif' => 1],
            ['kode_lks' => 'UA22001', 'subyek' => 'LTI SENTRAL YAMAHA', 'grup' => 'LT', 'singkatan' => 'CTL', 'kota' => 'Bandar Lampung', 'nonaktif' => 0],
            ['kode_lks' => 'UA22002', 'subyek' => 'LTI PAHOMAN', 'grup' => 'LT', 'singkatan' => 'PHM', 'kota' => 'Pahoman', 'nonaktif' => 0],
            ['kode_lks' => 'UA22003', 'subyek' => 'LTI KARANG ANYAR', 'grup' => 'LT', 'singkatan' => 'KRA', 'kota' => 'Karang Anyar', 'nonaktif' => 0],
            ['kode_lks' => 'UA22003A', 'subyek' => 'LTI NATAR', 'grup' => 'LT', 'singkatan' => 'NTR', 'kota' => 'Natar', 'nonaktif' => 0],
            ['kode_lks' => 'UB22001', 'subyek' => 'LTI METRO', 'grup' => 'LT', 'singkatan' => 'MTR', 'kota' => 'Metro', 'nonaktif' => 0],
            ['kode_lks' => 'UB22001A', 'subyek' => 'LTI PEKALONGAN', 'grup' => 'LT', 'singkatan' => 'PKL', 'kota' => 'Pekalongan', 'nonaktif' => 0],
            ['kode_lks' => 'UB22001B', 'subyek' => 'LTI IMOPURO', 'grup' => 'LT', 'singkatan' => 'STM', 'kota' => 'Imopuro', 'nonaktif' => 1],
            ['kode_lks' => 'UC22001', 'subyek' => 'LTI KALIANDA', 'grup' => 'LT', 'singkatan' => 'KLD', 'kota' => 'Kalianda', 'nonaktif' => 0],
            ['kode_lks' => 'UC22001A', 'subyek' => 'LTI PATOK', 'grup' => 'LT', 'singkatan' => 'PTK', 'kota' => 'Patok', 'nonaktif' => 0],
            ['kode_lks' => 'UC22002', 'subyek' => 'LTI PEMATANG PASIR', 'grup' => 'LT', 'singkatan' => 'PPS', 'kota' => 'Pematang Pasir', 'nonaktif' => 0],
            ['kode_lks' => 'UCUB001', 'subyek' => 'LTI GEDUNG TATAAN', 'grup' => 'LT', 'singkatan' => 'GDT', 'kota' => 'Gedung Tataan', 'nonaktif' => 0],
            ['kode_lks' => 'UCUB001A', 'subyek' => 'LTI WIYONO', 'grup' => 'LT', 'singkatan' => 'WYN', 'kota' => 'Wiyono', 'nonaktif' => 1],
            ['kode_lks' => 'UD00003', 'subyek' => 'LTI RAWAJITU', 'grup' => 'LT', 'singkatan' => 'RWJ', 'kota' => 'Rawajitu', 'nonaktif' => 0],
            ['kode_lks' => 'UDUA001', 'subyek' => 'LTI UNIT DUA', 'grup' => 'LT', 'singkatan' => 'TLB', 'kota' => 'Tulang Bawang', 'nonaktif' => 0],
            ['kode_lks' => 'UDUA001A', 'subyek' => 'LTI GUNUNG TERANG', 'grup' => 'LT', 'singkatan' => 'GNT', 'kota' => 'Gunung Terang', 'nonaktif' => 1],
            ['kode_lks' => 'UDUA002', 'subyek' => 'LTI MENGGALA', 'grup' => 'LT', 'singkatan' => 'MGL', 'kota' => 'Menggala', 'nonaktif' => 0],
            ['kode_lks' => 'UDUA002A', 'subyek' => 'LTI DAYA MURNI', 'grup' => 'LT', 'singkatan' => 'DYM', 'kota' => 'Daya Murni', 'nonaktif' => 1],
            ['kode_lks' => 'UDUB001A', 'subyek' => 'LTI BRABASAN', 'grup' => 'LT', 'singkatan' => 'BRB', 'kota' => 'Brabasan', 'nonaktif' => 1],
            ['kode_lks' => 'UDUB002', 'subyek' => 'LTI BANJAR AGUNG', 'grup' => 'LT', 'singkatan' => 'BJA', 'kota' => 'Banjar Agung', 'nonaktif' => 1],
            ['kode_lks' => 'UE22001', 'subyek' => 'LTI BANDAR JAYA', 'grup' => 'LT', 'singkatan' => 'BDJ', 'kota' => 'Bandar Jaya', 'nonaktif' => 0],
            ['kode_lks' => 'UE22002', 'subyek' => 'LTI KOTA GAJAH', 'grup' => 'LT', 'singkatan' => 'KTG', 'kota' => 'Kota Gajah', 'nonaktif' => 0],
            ['kode_lks' => 'UE22002A', 'subyek' => 'LTI PUNGGUR', 'grup' => 'LT', 'singkatan' => 'PGR', 'kota' => 'Punggur', 'nonaktif' => 0],
            ['kode_lks' => 'UE22003', 'subyek' => 'LTI RUMBIA', 'grup' => 'LT', 'singkatan' => 'RBA', 'kota' => 'Rumbia', 'nonaktif' => 0],
            ['kode_lks' => 'UE22003A', 'subyek' => 'LTI GAYA BARU', 'grup' => 'LT', 'singkatan' => 'GBR', 'kota' => 'Gaya Baru', 'nonaktif' => 1],
            ['kode_lks' => 'UFUA001', 'subyek' => 'LTI KOTABUMI', 'grup' => 'LT', 'singkatan' => 'KTB', 'kota' => 'Kotabumi', 'nonaktif' => 0],
            ['kode_lks' => 'UFUA001B', 'subyek' => 'LTI BUNGA MAYANG', 'grup' => 'LT', 'singkatan' => 'BGM', 'kota' => 'Bunga Mayang', 'nonaktif' => 1],
            ['kode_lks' => 'UFUA001C', 'subyek' => 'LTI PAKUAN RATU', 'grup' => 'LT', 'singkatan' => 'PKR', 'kota' => 'Pakuan Ratu', 'nonaktif' => 1],
            ['kode_lks' => 'UHUB001', 'subyek' => 'LTI SRIBHAWONO', 'grup' => 'LT', 'singkatan' => 'SBW', 'kota' => 'Sribhawono', 'nonaktif' => 0],
            ['kode_lks' => 'UHUB001A', 'subyek' => 'LTI TRIDATU', 'grup' => 'LT', 'singkatan' => 'TDT', 'kota' => 'Tridatu', 'nonaktif' => 1],
            ['kode_lks' => 'UHUE002', 'subyek' => 'LTI MARGATIGA', 'grup' => 'LT', 'singkatan' => 'MGT', 'kota' => 'Margatiga', 'nonaktif' => 1],
            ['kode_lks' => 'UIUB001', 'subyek' => 'LTI PRINGSEWU', 'grup' => 'LT', 'singkatan' => 'PSW', 'kota' => 'Pringsewu', 'nonaktif' => 0],
            ['kode_lks' => 'UIUB002', 'subyek' => 'LTI KALIREJO', 'grup' => 'LT', 'singkatan' => 'KLJ', 'kota' => 'Kalirejo', 'nonaktif' => 0],
            ['kode_lks' => 'UKUA001', 'subyek' => 'LTI BLAMBANGAN UMPU', 'grup' => 'LT', 'singkatan' => 'BMP', 'kota' => 'Blambangan Umpu', 'nonaktif' => 0],
        ];

        foreach ($dealersData as $dealer) {
            if (strtoupper($dealer['grup']) === 'LT') {
                Dealer::create([
                    'kode_dealer' => $dealer['kode_lks'],
                    'nama_dealer' => $dealer['subyek'],
                    'grup' => $dealer['grup'],
                    'kota' => $dealer['kota'],
                    'singkatan' => $dealer['singkatan'],
                    'is_active' => $dealer['nonaktif'] == 0,
                ]);
            }
        }
    }
}