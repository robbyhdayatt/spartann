<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyPenjualanDetailsForConverts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('penjualan_details', function (Blueprint $table) {
            // 1. Tambahkan kolom convert_id
            $table->foreignId('convert_id')
                  ->nullable()
                  ->after('penjualan_id')
                  ->constrained('converts') // Pastikan nama tabel Anda 'converts'
                  ->onDelete('set null');

            // 2. Buat part_id dan rak_id menjadi nullable
            $table->foreignId('part_id')->nullable()->change();
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
            // Hati-hati saat rollback, cek nama constraint
            // $table->dropForeign(['convert_id']);
            $table->dropColumn('convert_id');

            // Kembalikan ke state semula (jika sebelumnya not nullable)
            $table->foreignId('part_id')->nullable(false)->change();
            $table->foreignId('rak_id')->nullable(false)->change();
        });
    }
}
