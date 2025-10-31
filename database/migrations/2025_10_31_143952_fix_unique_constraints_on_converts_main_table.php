<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixUniqueConstraintsOnConvertsMainTable extends Migration
{
/**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('converts_main', function (Blueprint $table) {

            // SEMUA PERINTAH dropUnique() DIHAPUS
            // KARENA INDEX YANG SALAH SUDAH TERHAPUS.
            // KITA HANYA PERLU MENJALANKAN LANGKAH INI:

            // 1. TAMBAHKAN COMPOSITE UNIQUE INDEX YANG BARU
            // Ini memastikan kombinasi 'nama_job' dan 'part_code' selalu unik
            $table->unique(['nama_job', 'part_code']);
        });
    }

/**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('converts_main', function (Blueprint $table) {
            // 1. Hapus composite index yang baru
            $table->dropUnique('converts_main_nama_job_part_code_unique');

            // 2. (Opsional) Tambahkan kembali unique index 'part_code' yang lama
            // $table->unique('part_code', 'converts_part_code_input_unique');
        });
    }
}
