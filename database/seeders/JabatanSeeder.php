<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Jabatan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class JabatanSeeder extends Seeder
{
    public function run()
    {
        Schema::disableForeignKeyConstraints();
        DB::table('jabatans')->truncate();
        Schema::enableForeignKeyConstraints();

        $jabatans = [
            // === Level Pusat / Global ===
            ['nama_jabatan' => 'Super Admin', 'singkatan' => 'SA'],
            ['nama_jabatan' => 'PIC', 'singkatan' => 'PIC'],
            
            // Masuk ke Lokasi MAIN DEALER (KANTOR PUSAT)
            ['nama_jabatan' => 'Area Service Development', 'singkatan' => 'ASD'],
            ['nama_jabatan' => 'Inventory MD Shop', 'singkatan' => 'IMS'],
            ['nama_jabatan' => 'Accounting MD', 'singkatan' => 'ACC'],

            // === Level Gudang (GUDANG PART) ===
            ['nama_jabatan' => 'Kepala Gudang', 'singkatan' => 'KG'],
            ['nama_jabatan' => 'Admin Gudang', 'singkatan' => 'AG'],

            // === Level Dealer ===
            ['nama_jabatan' => 'Kepala Cabang', 'singkatan' => 'KC'],
            ['nama_jabatan' => 'Part Counter', 'singkatan' => 'PC'],
            ['nama_jabatan' => 'Kasir', 'singkatan' => 'KSR'],
        ];

        foreach ($jabatans as $jabatan) {
            Jabatan::create($jabatan);
        }
    }
}