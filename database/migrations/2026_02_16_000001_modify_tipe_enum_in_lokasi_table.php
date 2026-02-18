<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ModifyTipeEnumInLokasiTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Karena mengubah ENUM di beberapa database (seperti MySQL) butuh raw query atau package doctrine/dbal
        // Kita gunakan DB::statement untuk aman dan langsung.
        
        // 1. Ubah definisi kolom tipe menjadi enum('PUSAT', 'DEALER', 'GUDANG')
        // Pastikan urutan dan default value sesuai keinginan.
        DB::statement("ALTER TABLE lokasi MODIFY COLUMN tipe ENUM('PUSAT', 'DEALER', 'GUDANG') NOT NULL DEFAULT 'DEALER'");
        
        // 2. (Opsional) Update data lama yang seharusnya jadi GUDANG
        // Contoh: Lokasi dengan nama 'MAIN DEALER PART' kita ubah jadi tipe 'GUDANG'
        DB::table('lokasi')
            ->where('nama_lokasi', 'LIKE', '%PART%') // Asumsi nama gudang ada kata PART
            ->orWhere('kode_lokasi', 'GUDANG PART')
            ->update(['tipe' => 'GUDANG']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Kembalikan ke definisi lama jika rollback
        // Perhatian: Data dengan tipe 'GUDANG' mungkin akan error atau terpotong jika dipaksa balik tanpa update dulu.
        
        // Safety: Ubah dulu GUDANG jadi PUSAT sebelum revert enum
        DB::table('lokasi')->where('tipe', 'GUDANG')->update(['tipe' => 'PUSAT']);
        
        DB::statement("ALTER TABLE lokasi MODIFY COLUMN tipe ENUM('PUSAT', 'DEALER') NOT NULL DEFAULT 'DEALER'");
    }
}