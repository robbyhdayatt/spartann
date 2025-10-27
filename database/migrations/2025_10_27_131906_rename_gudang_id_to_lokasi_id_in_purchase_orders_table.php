<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameGudangIdToLokasiIdInPurchaseOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Cek jika foreign key constraint ada sebelum menghapusnya
            // Nama constraint bisa bervariasi (misal: purchase_orders_gudang_id_foreign)
            // Anda mungkin perlu mengecek nama constraint di skema database Anda
            // $sm = Schema::getConnection()->getDoctrineSchemaManager();
            // $foreignKeys = array_map(function($fk) {
            //     return $fk->getName();
            // }, $sm->listTableForeignKeys('purchase_orders'));

            // if (in_array('purchase_orders_gudang_id_foreign', $foreignKeys)) {
            //    $table->dropForeign(['gudang_id']);
            // }

            // Ubah nama kolom 'gudang_id' menjadi 'lokasi_id'
            $table->renameColumn('gudang_id', 'lokasi_id');

            // Tambahkan kembali foreign key constraint dengan nama kolom baru
            // Pastikan tabel 'lokasi' sudah ada
            // Sesuaikan onDelete jika perlu (misal: cascade, set null)
            // $table->foreign('lokasi_id')->references('id')->on('lokasi')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Hapus constraint baru jika Anda menambahkannya di method up()
            // $table->dropForeign(['lokasi_id']);

            // Kembalikan nama kolom 'lokasi_id' menjadi 'gudang_id'
            $table->renameColumn('lokasi_id', 'gudang_id');

            // Tambahkan kembali constraint lama jika Anda menghapusnya di method up()
            // $table->foreign('gudang_id')->references('id')->on('lokasi')->onDelete('restrict');
        });
    }
}
