<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixNullableOldColumnsInPenjualanDetails extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('penjualan_details', function (Blueprint $table) {
            // Paksa kolom part_id untuk mengizinkan NULL
            $table->foreignId('part_id')->nullable()->change();

            // Paksa kolom rak_id untuk mengizinkan NULL (ini akan error selanjutnya jika tidak diperbaiki)
            $table->foreignId('rak_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('penjualan_details', function (Blueprint $table) {
            // Kembalikan ke state semula (NOT NULL) jika di-rollback
            $table->foreignId('part_id')->nullable(false)->change();
            $table->foreignId('rak_id')->nullable(false)->change();
        });
    }
}
