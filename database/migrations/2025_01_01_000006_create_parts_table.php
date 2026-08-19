<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePartsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            $table->string('kode_part', 255)->unique();
            $table->string('nama_part', 255)->index('idx_parts_nama_part');
            $table->integer('stok_minimum')->default(0);
            $table->integer('qty_stok')->default(0);
            $table->double('cost')->nullable()->default(0);
            $table->decimal('retail', 15, 2)->default(0.00);
            $table->boolean('is_active')->default(true)->index('idx_parts_is_active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('parts');
    }
}
