<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyPriceColumnsInPartsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('parts', function (Blueprint $table) {
            // 1. Tambahkan kolom baru terlebih dahulu
            // Kita tempatkan setelah kolom 'stok_minimum'
            $table->decimal('dpp', 15, 2)->default(0.00)->after('stok_minimum');
            $table->decimal('ppn', 15, 2)->default(0.00)->after('dpp');
            $table->decimal('harga_satuan', 15, 2)->default(0.00)->after('ppn');

            // 2. Hapus kolom harga yang lama
            $table->dropColumn('harga_beli_default');
            $table->dropColumn('harga_beli_rata_rata');
            $table->dropColumn('harga_jual_default');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('parts', function (Blueprint $table) {
            // 1. Tambahkan kembali kolom yang lama
            $table->decimal('harga_beli_default', 15, 2)->default(0.00)->after('stok_minimum');
            $table->decimal('harga_beli_rata_rata', 15, 2)->default(0.00)->after('harga_beli_default');
            $table->decimal('harga_jual_default', 15, 2)->default(0.00)->after('harga_beli_rata_rata');

            // 2. Hapus kolom yang baru
            $table->dropColumn('dpp');
            $table->dropColumn('ppn');
            $table->dropColumn('harga_satuan');
        });
    }
}