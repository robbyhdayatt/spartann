<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyGudangsForRombakV1 extends Migration
{
    public function up()
    {
        // 1. Ganti nama tabel dari 'gudangs' menjadi 'lokasi'
        Schema::rename('gudangs', 'lokasi');

        // 2. Tambahkan kolom 'tipe' untuk membedakan Gudang Pusat dan Dealer
        Schema::table('lokasi', function (Blueprint $table) {
            $table->enum('tipe', ['PUSAT', 'DEALER'])->default('DEALER')->after('id');
        });
    }

    public function down()
    {
        Schema::table('lokasi', function (Blueprint $table) {
            $table->dropColumn('tipe');
        });

        Schema::rename('lokasi', 'gudangs');
    }
}
