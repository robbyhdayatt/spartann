<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyRaksStructure extends Migration
{
    public function up()
    {
        Schema::table('raks', function (Blueprint $table) {
            // Hapus unique constraint lama jika ada (tergantung nama index di db anda, biasanya raks_gudang_id_kode_rak_unique)
            // $table->dropUnique(['gudang_id', 'kode_rak']); 
            
            // Tambahkan kolom detail posisi
            $table->string('zona', 5)->after('lokasi_id')->comment('Ex: A');
            $table->string('nomor_rak', 5)->after('zona')->comment('Ex: R01');
            $table->string('level', 5)->after('nomor_rak')->comment('Ex: L1');
            $table->string('bin', 5)->after('level')->comment('Ex: B01');
            
            // Ubah kode_rak jadi lebih panjang untuk menampung format gabungan
            $table->string('kode_rak', 50)->change();
        });
    }

    public function down()
    {
        Schema::table('raks', function (Blueprint $table) {
            $table->dropColumn(['zona', 'nomor_rak', 'level', 'bin']);
        });
    }
}