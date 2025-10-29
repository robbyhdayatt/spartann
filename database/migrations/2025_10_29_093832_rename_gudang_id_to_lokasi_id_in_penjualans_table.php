<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameGudangIdToLokasiIdInPenjualansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('penjualans', function (Blueprint $table) {
            // Hapus foreign key lama (pastikan nama constraint-nya benar)
            // Nama default: penjualans_gudang_id_foreign
            // $table->dropForeign(['gudang_id']);

            // Ubah nama kolom
            $table->renameColumn('gudang_id', 'lokasi_id');

            // Tambahkan foreign key baru (opsional tapi disarankan)
            // $table->foreign('lokasi_id')
            //       ->references('id')
            //       ->on('lokasi')
            //       ->onDelete('restrict'); // atau set null
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('penjualans', function (Blueprint $table) {
            // $table->dropForeign(['lokasi_id']);
            $table->renameColumn('lokasi_id', 'gudang_id');
            // $table->foreign('gudang_id')->references('id')->on('lokasi');
        });
    }
}
