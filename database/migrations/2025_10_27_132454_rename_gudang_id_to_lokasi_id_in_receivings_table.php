<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameGudangIdToLokasiIdInReceivingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('receivings', function (Blueprint $table) {
            // Ubah nama kolom
            $table->renameColumn('gudang_id', 'lokasi_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('receivings', function (Blueprint $table) {
            // Kembalikan nama kolom
            $table->renameColumn('lokasi_id', 'gudang_id');
        });
    }
}
