<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStokMinimumToBarangs extends Migration
{
    public function up()
    {
        Schema::table('barangs', function (Blueprint $table) {
            // Tambahkan kolom stok_minimum jika belum ada
            if (!Schema::hasColumn('barangs', 'stok_minimum')) {
                $table->integer('stok_minimum')->default(10)->after('part_code');
            }
        });
    }

    public function down()
    {
        Schema::table('barangs', function (Blueprint $table) {
            $table->dropColumn('stok_minimum');
        });
    }
}
