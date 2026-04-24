<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            $table->string('kode_part')->unique();
            $table->string('nama_part');
            $table->integer('stok_minimum')->default(0);
            $table->integer('qty_stok')->default(0);
            $table->decimal('retail', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('parts');
    }
};