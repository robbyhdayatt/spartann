<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameGudangIdToLokasiIdInSalesReturnsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sales_returns', function (Blueprint $table) {
            // Hapus foreign key lama (pastikan nama constraint-nya benar)
            // Nama constraint dari error: sales_returns_gudang_id_foreign
            // $table->dropForeign('sales_returns_gudang_id_foreign');

            // Ubah nama kolom
            $table->renameColumn('gudang_id', 'lokasi_id');

            // Tambahkan foreign key baru (opsional tapi disarankan)
            // $table->foreign('lokasi_id')
            //       ->references('id')
            //       ->on('lokasi')
            //       ->onDelete('restrict'); // atau set null
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sales_returns', function (Blueprint $table) {
            // $table->dropForeign(['lokasi_id']);
            $table->renameColumn('lokasi_id', 'gudang_id');
            // $table->foreign('gudang_id', 'sales_returns_gudang_id_foreign')->references('id')->on('lokasi');
        });
    }
}
