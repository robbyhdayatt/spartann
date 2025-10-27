<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReceivingDetailIdToPurchaseReturnDetailsTable extends Migration
{
    public function up()
    {
        Schema::table('purchase_return_details', function (Blueprint $table) {
            // Tambahkan setelah part_id atau kolom relevan lainnya
            $table->foreignId('receiving_detail_id')
                  ->nullable() // Atau sesuaikan jika wajib
                  ->after('part_id')
                  ->constrained('receiving_details')
                  ->onDelete('set null'); // Atau cascade/restrict
        });
    }

    public function down()
    {
        Schema::table('purchase_return_details', function (Blueprint $table) {
            // Hati-hati saat drop constraint, pastikan nama benar
            // Nama default: purchase_return_details_receiving_detail_id_foreign
            // $table->dropForeign(['receiving_detail_id']);
            $table->dropColumn('receiving_detail_id');
        });
    }
}