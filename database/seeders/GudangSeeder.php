<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema; // 1. Tambahkan ini

class GudangSeeder extends Seeder
{
    public function run()
    {
        // 2. Nonaktifkan pengecekan foreign key
        Schema::disableForeignKeyConstraints();

        DB::table('lokasi')->truncate();

        // 3. Aktifkan kembali pengecekan foreign key
        Schema::enableForeignKeyConstraints();

        // Buat Gudang Pusat
        DB::table('lokasi')->insert([
            'tipe' => 'PUSAT',
            'kode_gudang' => 'GSP',
            'nama_gudang' => 'Gudang Pusat (Sentral)',
            'alamat' => 'Jl. Ikan Tenggiri No. 24, Bandar Lampung',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
