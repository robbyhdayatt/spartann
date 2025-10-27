<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameColumnsInLokasiTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lokasi', function (Blueprint $table) {
            // Ubah nama kolom 'kode_gudang' menjadi 'kode_lokasi'
            $table->renameColumn('kode_gudang', 'kode_lokasi');

            // Ubah nama kolom 'nama_gudang' menjadi 'nama_lokasi'
            $table->renameColumn('nama_gudang', 'nama_lokasi');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lokasi', function (Blueprint $table) {
            // Kembalikan nama kolom 'kode_lokasi' menjadi 'kode_gudang'
            $table->renameColumn('kode_lokasi', 'kode_gudang');

            // Kembalikan nama kolom 'nama_lokasi' menjadi 'nama_gudang'
            $table->renameColumn('nama_lokasi', 'nama_gudang');
        });
    }
}
