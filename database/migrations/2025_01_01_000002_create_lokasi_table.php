<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLokasiTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lokasi', function (Blueprint $table) {
            $table->id();
            $table->enum('tipe', ['PUSAT', 'DEALER', 'GUDANG'])->default('DEALER');
            $table->string('kode_lokasi', 50)->unique();
            $table->string('nama_lokasi', 100);
            $table->string('singkatan', 10)->nullable();
            $table->string('npwp', 255)->nullable();
            $table->text('alamat')->nullable();
            $table->string('koadmin', 50)->nullable();
            $table->string('asd', 50)->nullable();
            $table->string('aom', 50)->nullable();
            $table->string('asm', 50)->nullable();
            $table->string('gm', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lokasi');
    }
}
