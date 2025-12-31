<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Hapus Foreign Key 'part_id'
        Schema::table('penjualan_details', function (Blueprint $table) {
            // Syntax ini otomatis mencari index bernama 'penjualan_details_part_id_foreign'
            // dan menghapusnya.
            $table->dropForeign(['part_id']);
        });

        // 2. Hapus Foreign Key 'convert_id' (Gunakan try-catch agar tidak error jika tidak ada)
        try {
            Schema::table('penjualan_details', function (Blueprint $table) {
                $table->dropForeign(['convert_id']);
            });
        } catch (\Exception $e) {
            // Abaikan jika FK convert_id tidak ditemukan/sudah terhapus
        }

        // 3. Setelah FK hilang, baru Hapus Kolomnya
        Schema::table('penjualan_details', function (Blueprint $table) {
            $table->dropColumn(['convert_id', 'part_id']);
        });
    }

    public function down()
    {
        // Tidak perlu rollback untuk cleanup ini
    }
};