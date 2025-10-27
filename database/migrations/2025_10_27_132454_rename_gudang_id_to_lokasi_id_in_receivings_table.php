<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameGudangIdToLokasiIdInReceivingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('receivings', function (Blueprint $table) {
            // Opsional: Hapus foreign key constraint jika ada
            // Pastikan nama constraint 'receivings_gudang_id_foreign' sudah benar
            // try {
            //     $table->dropForeign(['gudang_id']);
            // } catch (\Exception $e) {
            //     // Handle error jika constraint tidak ada atau nama berbeda
            //     $this->command->warn('Could not drop foreign key for gudang_id: ' . $e->getMessage());
            // }

            // Ubah nama kolom
            $table->renameColumn('gudang_id', 'lokasi_id');

            // Opsional: Tambahkan kembali foreign key constraint dengan nama baru
            // $table->foreign('lokasi_id')
            //       ->references('id')
            //       ->on('lokasi') // Pastikan merujuk ke tabel 'lokasi'
            //       ->onDelete('restrict'); // atau 'set null', 'cascade' sesuai kebutuhan
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('receivings', function (Blueprint $table) {
            // Opsional: Hapus foreign key constraint baru
            // try {
            //    $table->dropForeign(['lokasi_id']);
            // } catch (\Exception $e) {
            //     $this->command->warn('Could not drop foreign key for lokasi_id: ' . $e->getMessage());
            // }


            // Kembalikan nama kolom
            $table->renameColumn('lokasi_id', 'gudang_id');

            // Opsional: Tambahkan kembali foreign key constraint lama
            // $table->foreign('gudang_id')
            //       ->references('id')
            //       ->on('lokasi') // atau 'gudangs' jika tabel belum di-rename saat rollback
            //       ->onDelete('restrict');
        });
    }
}
