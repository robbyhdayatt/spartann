<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLokasiIdToServicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('services', function (Blueprint $table) {
            // Menambahkan kolom foreign key 'lokasi_id'
            $table->foreignId('lokasi_id')
                  ->nullable() // nullable agar data lama tidak error
                  ->after('dealer_code')
                  ->constrained('lokasi') // terhubung ke tabel 'lokasi'
                  ->onDelete('set null'); // jika lokasi dihapus, set kolom ini jadi null
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('services', function (Blueprint $table) {
            // Perintah untuk membatalkan migrasi
            $table->dropForeign(['lokasi_id']);
            $table->dropColumn('lokasi_id');
        });
    }
}