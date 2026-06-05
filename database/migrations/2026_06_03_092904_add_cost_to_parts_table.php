<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCostToPartsTable extends Migration
{
    public function up()
    {
        Schema::table('parts', function (Blueprint $table) {
            // Menambahkan kolom cost (Harga Modal) sebelum kolom retail (Harga Jual)
            $table->double('cost')->default(0)->after('qty_stok');
        });
    }

    public function down()
    {
        Schema::table('parts', function (Blueprint $table) {
            $table->dropColumn('cost');
        });
    }
}