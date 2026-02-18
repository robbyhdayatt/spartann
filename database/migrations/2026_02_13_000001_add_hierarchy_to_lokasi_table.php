<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // Tambahkan ini

class AddHierarchyToLokasiTable extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE lokasi MODIFY kode_lokasi VARCHAR(50) NOT NULL');

        Schema::table('lokasi', function (Blueprint $table) {
            if (!Schema::hasColumn('lokasi', 'singkatan')) {
                $table->string('singkatan', 10)->nullable()->after('nama_lokasi');
                $table->string('koadmin', 50)->nullable()->after('alamat');
                $table->string('asd', 50)->nullable()->after('koadmin');
                $table->string('aom', 50)->nullable()->after('asd');
                $table->string('asm', 50)->nullable()->after('aom');
                $table->string('gm', 50)->nullable()->after('asm');
            }
        });
    }

    public function down()
    {
        Schema::table('lokasi', function (Blueprint $table) {
            $table->dropColumn(['singkatan', 'koadmin', 'asd', 'aom', 'asm', 'gm']);
        });
    }
}