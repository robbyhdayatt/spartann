<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameGudangIdToLokasiIdInRaksTable extends Migration
{
    public function up()
    {
        Schema::table('raks', function (Blueprint $table) {
            // Cek jika foreign key constraint ada sebelum menghapusnya
            // Nama constraint bisa bervariasi, cek skema Anda jika nama ini salah
            // Contoh nama: raks_gudang_id_foreign
            // $sm = Schema::getConnection()->getDoctrineSchemaManager();
            // $foreignKeys = array_map(function($fk) {
            //     return $fk->getName();
            // }, $sm->listTableForeignKeys('raks'));

            // if (in_array('raks_gudang_id_foreign', $foreignKeys)) {
            //     $table->dropForeign(['gudang_id']);
            // }

            $table->renameColumn('gudang_id', 'lokasi_id');

            // Tambahkan kembali foreign key constraint dengan nama kolom baru
            // $table->foreign('lokasi_id')->references('id')->on('lokasi')->onDelete('cascade'); // Sesuaikan onDelete jika perlu
        });
    }

    public function down()
    {
        Schema::table('raks', function (Blueprint $table) {
            // $table->dropForeign(['lokasi_id']); // Hapus constraint baru
            $table->renameColumn('lokasi_id', 'gudang_id');
            // $table->foreign('gudang_id')->references('id')->on('lokasi')->onDelete('cascade'); // Tambahkan kembali constraint lama
        });
    }
}
