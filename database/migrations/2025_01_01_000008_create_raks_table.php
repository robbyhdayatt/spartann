<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRaksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('raks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lokasi_id')->constrained('lokasi')->cascadeOnDelete();
            $table->string('zona', 5)->comment('Ex: A');
            $table->string('nomor_rak', 5)->comment('Ex: R01');
            $table->string('level', 5)->comment('Ex: L1');
            $table->string('bin', 5)->comment('Ex: B01');
            $table->string('kode_rak', 50)->nullable();
            $table->enum('tipe_rak', ['PENYIMPANAN', 'KARANTINA'])->default('PENYIMPANAN');
            $table->string('nama_rak', 100)->nullable();
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
        Schema::dropIfExists('raks');
    }
}
