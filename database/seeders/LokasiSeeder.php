<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Lokasi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LokasiSeeder extends Seeder
{
    public function run()
    {
        Schema::disableForeignKeyConstraints();
        DB::table('lokasi')->truncate();
        Schema::enableForeignKeyConstraints();

        $data = [
            // GUDANG PUSAT / MAIN DEALER
            [
                'kode_lokasi' => 'GUDANG PART',
                'nama_lokasi' => 'MAIN DEALER PART',
                'singkatan'   => 'MDP',
                'tipe'        => 'GUDANG',
                'is_active'   => 1,
                // Data hierarki kosong untuk pusat sesuai tabel
                'koadmin' => null, 'asd' => null, 'aom' => null, 'asm' => null, 'gm' => null
            ],
            [
                'kode_lokasi' => 'KANTOR PUSAT',
                'nama_lokasi' => 'MAIN DEALER',
                'singkatan'   => 'MD',
                'tipe'        => 'PUSAT',
                'is_active'   => 1,
                'koadmin' => null, 'asd' => null, 'aom' => null, 'asm' => null, 'gm' => null
            ],
            // DEALER CABANG
            ['kode_lokasi' => 'UE22001', 'nama_lokasi' => 'LTI BANDAR JAYA', 'singkatan' => 'BDJ', 'koadmin' => 'koadmin12', 'asd' => 'ridwan', 'aom' => 'chandra', 'asm' => 'asm2', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => 'UKUA001', 'nama_lokasi' => 'LTI BLAMBANGAN UMPU', 'singkatan' => 'BMP', 'koadmin' => 'koadmin21', 'asd' => 'rizky', 'aom' => 'henry', 'asm' => 'asm3', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => 'UCUB001', 'nama_lokasi' => 'LTI GEDUNG TATAAN', 'singkatan' => 'GDT', 'koadmin' => 'koadmin11', 'asd' => 'ridwan', 'aom' => 'evin', 'asm' => 'asm1', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => 'UC22001', 'nama_lokasi' => 'LTI KALIANDA', 'singkatan' => 'KLD', 'koadmin' => 'koadmin21', 'asd' => 'rizky', 'aom' => 'evin', 'asm' => 'asm1', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => 'UIUB002', 'nama_lokasi' => 'LTI KALIREJO', 'singkatan' => 'KLJ', 'koadmin' => 'koadmin13', 'asd' => 'ridwan', 'aom' => 'chandra', 'asm' => 'asm2', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => 'UA22003', 'nama_lokasi' => 'LTI KARANG ANYAR', 'singkatan' => 'KRA', 'koadmin' => 'koadmin23', 'asd' => 'rizky', 'aom' => 'evin', 'asm' => 'asm1', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => '9HL003', 'nama_lokasi' => 'LTI KEDATON', 'singkatan' => 'KDT', 'koadmin' => 'koadmin22', 'asd' => 'rudi', 'aom' => 'henry', 'asm' => 'asm3', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => '9HL008', 'nama_lokasi' => 'LTI KOTA AGUNG', 'singkatan' => 'KTA', 'koadmin' => 'koadmin11', 'asd' => 'rudi', 'aom' => 'evin', 'asm' => 'asm1', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => 'UE22002', 'nama_lokasi' => 'LTI KOTA GAJAH', 'singkatan' => 'KTG', 'koadmin' => 'koadmin13', 'asd' => 'ridwan', 'aom' => 'chandra', 'asm' => 'asm2', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => 'UFUA001', 'nama_lokasi' => 'LTI KOTABUMI', 'singkatan' => 'KTB', 'koadmin' => 'koadmin21', 'asd' => 'rizky', 'aom' => 'henry', 'asm' => 'asm3', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => '9HL002A', 'nama_lokasi' => 'LTI KRUI', 'singkatan' => 'KRI', 'koadmin' => 'koadmin22', 'asd' => 'rudi', 'aom' => 'henry', 'asm' => 'asm3', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => '9HL002', 'nama_lokasi' => 'LTI LIWA', 'singkatan' => 'LWA', 'koadmin' => 'koadmin22', 'asd' => 'rudi', 'aom' => 'henry', 'asm' => 'asm3', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => '9HL006', 'nama_lokasi' => 'LTI MANDALA', 'singkatan' => 'MDL', 'koadmin' => 'koadmin11', 'asd' => 'rizky', 'aom' => 'chandra', 'asm' => 'asm2', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => 'UDUA002', 'nama_lokasi' => 'LTI MENGGALA', 'singkatan' => 'MGL', 'koadmin' => 'koadmin22', 'asd' => 'ridwan', 'aom' => 'chandra', 'asm' => 'asm2', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => 'UB22001', 'nama_lokasi' => 'LTI METRO', 'singkatan' => 'MTR', 'koadmin' => 'koadmin12', 'asd' => 'ridwan', 'aom' => 'chandra', 'asm' => 'asm2', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => 'UA22003A', 'nama_lokasi' => 'LTI NATAR', 'singkatan' => 'NTR', 'koadmin' => 'koadmin23', 'asd' => 'rudi', 'aom' => 'evin', 'asm' => 'asm1', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => 'UA22002', 'nama_lokasi' => 'LTI PAHOMAN', 'singkatan' => 'PHM', 'koadmin' => 'koadmin13', 'asd' => 'ridwan', 'aom' => 'chandra', 'asm' => 'asm2', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => 'UC22001A', 'nama_lokasi' => 'LTI PATOK', 'singkatan' => 'PTK', 'koadmin' => 'koadmin23', 'asd' => 'rizky', 'aom' => 'evin', 'asm' => 'asm1', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => 'UB22001A', 'nama_lokasi' => 'LTI PEKALONGAN', 'singkatan' => 'PKL', 'koadmin' => 'koadmin12', 'asd' => 'ridwan', 'aom' => 'henry', 'asm' => 'asm3', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => 'UC22002', 'nama_lokasi' => 'LTI PEMATANG PASIR', 'singkatan' => 'PPS', 'koadmin' => 'koadmin23', 'asd' => 'rizky', 'aom' => 'evin', 'asm' => 'asm1', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => '9HL003B', 'nama_lokasi' => 'LTI PRAMUKA', 'singkatan' => 'PMK', 'koadmin' => 'koadmin22', 'asd' => 'rudi', 'aom' => 'henry', 'asm' => 'asm3', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => 'UIUB001', 'nama_lokasi' => 'LTI PRINGSEWU', 'singkatan' => 'PSW', 'koadmin' => 'koadmin23', 'asd' => 'ridwan', 'aom' => 'evin', 'asm' => 'asm1', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => 'UE22002A', 'nama_lokasi' => 'LTI PUNGGUR', 'singkatan' => 'PGR', 'koadmin' => 'koadmin13', 'asd' => 'ridwan', 'aom' => 'chandra', 'asm' => 'asm2', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => '9HL004', 'nama_lokasi' => 'LTI PURBOLINGGO', 'singkatan' => 'PBG', 'koadmin' => 'koadmin12', 'asd' => 'ridwan', 'aom' => 'henry', 'asm' => 'asm3', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => 'UD00003', 'nama_lokasi' => 'LTI RAWAJITU', 'singkatan' => 'RWJ', 'koadmin' => 'koadmin23', 'asd' => 'ridwan', 'aom' => 'chandra', 'asm' => 'asm2', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => 'UE22003', 'nama_lokasi' => 'LTI RUMBIA', 'singkatan' => 'RBA', 'koadmin' => 'koadmin11', 'asd' => 'rizky', 'aom' => 'chandra', 'asm' => 'asm2', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => '9HL004A', 'nama_lokasi' => 'LTI SEKAMPUNG', 'singkatan' => 'SKP', 'koadmin' => 'koadmin12', 'asd' => 'rizky', 'aom' => 'henry', 'asm' => 'asm3', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => 'UA22001', 'nama_lokasi' => 'LTI SENTRAL YAMAHA', 'singkatan' => 'CTL', 'koadmin' => 'koadmin11', 'asd' => 'rizky', 'aom' => 'evin', 'asm' => 'asm1', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => '9HL009', 'nama_lokasi' => 'LTI SIMPANG PEMATANG', 'singkatan' => 'SPM', 'koadmin' => 'koadmin21', 'asd' => 'ridwan', 'aom' => 'chandra', 'asm' => 'asm2', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => 'UHUB001', 'nama_lokasi' => 'LTI SRIBHAWONO', 'singkatan' => 'SBW', 'koadmin' => 'koadmin12', 'asd' => 'rizky', 'aom' => 'henry', 'asm' => 'asm3', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => '9HL002B', 'nama_lokasi' => 'LTI SUMBER JAYA', 'singkatan' => 'SBJ', 'koadmin' => 'koadmin22', 'asd' => 'rudi', 'aom' => 'henry', 'asm' => 'asm3', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => '9HL001', 'nama_lokasi' => 'LTI TANJUNG BINTANG', 'singkatan' => 'TJB', 'koadmin' => 'koadmin23', 'asd' => 'rudi', 'aom' => 'evin', 'asm' => 'asm1', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => '9HL007', 'nama_lokasi' => 'LTI TIRTAYASA', 'singkatan' => 'TTY', 'koadmin' => 'koadmin22', 'asd' => 'rudi', 'aom' => 'evin', 'asm' => 'asm1', 'gm' => 'iwan', 'is_active' => 1],
            ['kode_lokasi' => 'UDUA001', 'nama_lokasi' => 'LTI UNIT DUA', 'singkatan' => 'TLB', 'koadmin' => 'koadmin23', 'asd' => 'ridwan', 'aom' => 'chandra', 'asm' => 'asm2', 'gm' => 'iwan', 'is_active' => 1],
        ];

        foreach ($data as $item) {
            Lokasi::create([
                'tipe'        => $item['tipe'] ?? 'DEALER',
                'kode_lokasi' => $item['kode_lokasi'],
                'nama_lokasi' => $item['nama_lokasi'],
                'singkatan'   => $item['singkatan'],
                'npwp'        => null,
                'alamat'      => null, 
                'is_active'   => $item['is_active'],
                'koadmin'     => $item['koadmin'],
                'asd'         => $item['asd'],
                'aom'         => $item['aom'],
                'asm'         => $item['asm'],
                'gm'          => $item['gm'],
            ]);
        }
    }
}