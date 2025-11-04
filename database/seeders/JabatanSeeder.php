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
            // Level Pusat / Global
            ['nama_jabatan' => 'Super Admin', 'singkatan' => 'SA'],
            ['nama_jabatan' => 'PIC', 'singkatan' => 'PIC'],
            ['nama_jabatan' => 'Manajer Area', 'singkatan' => 'MA'],
            ['nama_jabatan' => 'Area Service Development', 'singkatan' => 'ASD'], // ++ TAMBAHKAN INI ++

            // Level Gudang Pusat
            ['nama_jabatan' => 'Kepala Gudang', 'singkatan' => 'KG'],
            ['nama_jabatan' => 'Admin Gudang', 'singkatan' => 'AG'],
            ['nama_jabatan' => 'Sales', 'singkatan' => 'SLS'],
            ['nama_jabatan' => 'Kasir', 'singkatan' => 'KSR'],

            // Level Dealer
            ['nama_jabatan' => 'Kepala Cabang', 'singkatan' => 'KC'],
            ['nama_jabatan' => 'Admin Dealer', 'singkatan' => 'AD'],
            ['nama_jabatan' => 'Counter Sales', 'singkatan' => 'CS'],
        ];

        foreach ($jabatans as $jabatan) {
            Jabatan::create($jabatan);
        }
    }
}