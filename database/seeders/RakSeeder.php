<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Lokasi; // Diubah dari Gudang
use App\Models\Rak;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RakSeeder extends Seeder
{
    public function run()
    {
        Schema::disableForeignKeyConstraints();
        DB::table('raks')->truncate();
        Schema::enableForeignKeyConstraints();

        // Ambil ID Gudang Pusat
        $gudangPusat = Lokasi::where('tipe', 'PUSAT')->first();

        if ($gudangPusat) {
            Rak::create(['gudang_id' => $gudangPusat->id, 'kode_rak' => 'A-01-01', 'nama_rak' => 'Rak A-01-01', 'tipe_rak' => 'PENYIMPANAN']);
            Rak::create(['gudang_id' => $gudangPusat->id, 'kode_rak' => 'A-01-02', 'nama_rak' => 'Rak A-01-02', 'tipe_rak' => 'PENYIMPANAN']);
            Rak::create(['gudang_id' => $gudangPusat->id, 'kode_rak' => 'KRN-QC', 'nama_rak' => 'Rak Karantina QC', 'tipe_rak' => 'KARANTINA']);
            Rak::create(['gudang_id' => $gudangPusat->id, 'kode_rak' => 'KRN-RT', 'nama_rak' => 'Rak Karantina Retur', 'tipe_rak' => 'KARANTINA']);
        }
    }
}
