<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBarangsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('barangs', function (Blueprint $table) {
            $table->id();
            $table->string('part_name', 255);
            $table->string('merk', 255)->nullable();
            $table->string('part_code', 255)->unique();
            $table->integer('stok_minimum')->default(10);
            $table->decimal('selling_in', 15, 2)->default(0.00);
            $table->decimal('selling_out', 15, 2)->default(0.00);
            $table->decimal('retail', 15, 2)->default(0.00);
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
        Schema::dropIfExists('barangs');
    }
}
