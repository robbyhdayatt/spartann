<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBarangIdToPenjualanDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('penjualan_details', function (Blueprint $table) {
            // Tambahkan kolom barang_id sebagai foreign key ke tabel 'barangs'
            $table->foreignId('barang_id')
                ->nullable()
                ->after('convert_id') // Letakkan setelah convert_id
                ->constrained('barangs') // Mereferensi ke tabel 'barangs'
                ->onDelete('set null');
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
            // Hati-hati saat rollback, ini adalah nama constraint default
            $table->dropForeign(['barang_id']);
            $table->dropColumn('barang_id');
        });
    }
}
