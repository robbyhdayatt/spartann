<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameGudangIdToLokasiIdInUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // $table->dropForeign(['gudang_id']); // Cek nama constraint jika perlu
            $table->renameColumn('gudang_id', 'lokasi_id');
            // $table->foreign('lokasi_id')->references('id')->on('lokasi')->onDelete('set null'); // Sesuaikan onDelete
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            // $table->dropForeign(['lokasi_id']);
            $table->renameColumn('lokasi_id', 'gudang_id');
            // $table->foreign('gudang_id')->references('id')->on('lokasi')->onDelete('set null');
        });
    }
}
