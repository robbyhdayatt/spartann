<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RefactorServiceDetailsToBarangs extends Migration
{
    public function up()
    {
        Schema::table('service_details', function (Blueprint $table) {
            // 1. Hapus kolom lama yang tidak relevan jika sudah pakai relation
            // Kita biarkan item_code sebagai historical text, tapi tambah barang_id

            if (!Schema::hasColumn('service_details', 'barang_id')) {
                $table->foreignId('barang_id')->nullable()->after('service_id')->constrained('barangs')->onDelete('set null');
            }

            // Tambahkan kolom untuk menyimpan HPP saat barang keluar (untuk laporan laba rugi)
            if (!Schema::hasColumn('service_details', 'cost_price')) {
                $table->decimal('cost_price', 15, 2)->default(0)->after('price');
            }
        });
    }

    public function down()
    {
        Schema::table('service_details', function (Blueprint $table) {
            $table->dropForeign(['barang_id']);
            $table->dropColumn(['barang_id', 'cost_price']);
        });
    }
}
