<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDealersTable extends Migration
{
    public function up()
    {
        Schema::create('dealers', function (Blueprint $table) {
            $table->id();
            $table->string('kode_dealer', 20)->unique();
            $table->string('nama_dealer');
            $table->string('grup', 50)->nullable();
            $table->string('kota', 100)->nullable();
            $table->string('singkatan', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('dealers');
    }
}
