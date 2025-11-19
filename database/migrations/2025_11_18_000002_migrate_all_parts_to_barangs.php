<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class MigrateAllPartsToBarangs extends Migration
{
    /**
     * Daftar tabel yang memiliki kolom part_id yang perlu diubah.
     * Urutan penting untuk menghindari error foreign key constraint.
     */
    protected $tables = [
        'inventory_batches',
        'stock_movements',
        'stock_mutations',
        'stock_adjustments',
        'purchase_order_details',
        'receiving_details',
        'purchase_return_details',
        'sales_return_details',
        'service_details',
        // 'penjualan_details' // Sudah ada barang_id (dari diskusi sebelumnya)
    ];

    public function up()
    {
        // Nonaktifkan check foreign key sementara agar proses alter lancar
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        foreach ($this->tables as $tableName) {
            if (Schema::hasColumn($tableName, 'part_id')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    // 1. Drop Foreign Key lama (format default laravel: nama_tabel_nama_kolom_foreign)
                    // Kita coba drop dengan array syntax yang lebih aman
                    $table->dropForeign([ 'part_id' ]);

                    // 2. Rename Kolom
                    $table->renameColumn('part_id', 'barang_id');
                });

                // 3. Tambah Foreign Key baru ke tabel 'barangs'
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreign('barang_id')
                          ->references('id')
                          ->on('barangs')
                          ->onDelete('cascade'); // Sesuaikan jika ingin 'restrict'
                });
            }
        }

        // Aktifkan kembali check foreign key
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function down()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        foreach ($this->tables as $tableName) {
            if (Schema::hasColumn($tableName, 'barang_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropForeign([ 'barang_id' ]);
                    $table->renameColumn('barang_id', 'part_id');
                });

                // Kembalikan FK ke tabel parts (jika tabel parts masih ada)
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreign('part_id')->references('id')->on('parts')->onDelete('cascade');
                });
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
