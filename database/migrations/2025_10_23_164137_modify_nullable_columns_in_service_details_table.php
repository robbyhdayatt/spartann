<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyNullableColumnsInServiceDetailsTable extends Migration
{
    public function up()
    {
        Schema::table('service_details', function (Blueprint $table) {
            $table->string('item_code')->nullable()->change();
            $table->string('item_name')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('service_details', function (Blueprint $table) {
            // Hati-hati: Mengembalikan ke NOT NULL bisa gagal jika ada data NULL
            // Mungkin perlu membersihkan data dulu atau membuat kolom tidak nullable
            // hanya jika Anda yakin tidak ada NULL. Untuk amannya, bisa dikomentari.
            // $table->string('item_code')->nullable(false)->change();
            // $table->string('item_name')->nullable(false)->change();
        });
    }
}
