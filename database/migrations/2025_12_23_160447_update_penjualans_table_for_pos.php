<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Update Tabel PENJUALANS
        Schema::table('penjualans', function (Blueprint $table) {
            // Tambah kolom created_by jika belum ada
            if (!Schema::hasColumn('penjualans', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('sales_id');
            }

            // Tambah kolom status
            if (!Schema::hasColumn('penjualans', 'status')) {
                $table->string('status')->default('COMPLETED')->after('sales_id');
            }

            // Tambah kolom keterangan_diskon
            if (!Schema::hasColumn('penjualans', 'keterangan_diskon')) {
                $table->string('keterangan_diskon')->nullable()->after('subtotal');
            }

            // Tambah kolom diskon (agar sesuai dengan controller)
            // Jika kolom total_diskon ada, kita biarkan saja atau rename, 
            // tapi amannya kita pastikan kolom 'diskon' tersedia untuk controller baru
            if (!Schema::hasColumn('penjualans', 'diskon')) {
                $table->decimal('diskon', 15, 2)->default(0)->after('subtotal');
            }
        });

        // 2. Update Tabel PENJUALAN_DETAILS
        Schema::table('penjualan_details', function (Blueprint $table) {
            // Ubah kolom lama menjadi NULLABLE agar tidak error saat insert
            // Karena logika baru hanya butuh barang_id
            
            if (Schema::hasColumn('penjualan_details', 'convert_id')) {
                $table->unsignedBigInteger('convert_id')->nullable()->change();
            }
            
            if (Schema::hasColumn('penjualan_details', 'part_id')) {
                $table->unsignedBigInteger('part_id')->nullable()->change();
            }

            if (Schema::hasColumn('penjualan_details', 'rak_id')) {
                $table->unsignedBigInteger('rak_id')->nullable()->change();
            }
            
            if (Schema::hasColumn('penjualan_details', 'qty_diretur')) {
                $table->integer('qty_diretur')->default(0)->change();
            }
        });
    }

    public function down()
    {
        // Rollback (Opsional, sesuaikan jika perlu)
        Schema::table('penjualans', function (Blueprint $table) {
            $table->dropColumn(['created_by', 'status', 'keterangan_diskon', 'diskon']);
        });
    }
};