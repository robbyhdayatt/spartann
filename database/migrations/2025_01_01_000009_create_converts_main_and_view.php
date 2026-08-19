<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateConvertsMainAndView extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('converts_main', function (Blueprint $table) {
            $table->id();
            $table->string('nama_job', 255)->index();
            $table->string('part_code', 255);
            $table->string('keterangan', 255)->nullable();
            $table->integer('quantity');
            $table->timestamps();
        });

        DB::statement("DROP VIEW IF EXISTS converts");
        DB::statement("
            CREATE VIEW converts AS 
            SELECT 
                cm.id, 
                cm.nama_job, 
                cm.keterangan, 
                cm.quantity, 
                cm.part_code, 
                b.part_name, 
                b.merk, 
                b.selling_in, 
                b.selling_out, 
                b.retail, 
                cm.created_at, 
                cm.updated_at 
            FROM converts_main cm 
            LEFT JOIN barangs b ON cm.part_code = b.part_code
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW IF EXISTS converts");
        Schema::dropIfExists('converts_main');
    }
}
