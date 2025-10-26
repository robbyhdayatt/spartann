<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLaborCostServiceToServiceDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('service_details', function (Blueprint $table) {
            // Tambahkan kolom baru setelah 'service_package_name'
            $table->decimal('labor_cost_service', 15, 2)
                  ->default(0.00) // Atur nilai default jika perlu
                  ->nullable()     // Buat nullable jika datanya mungkin kosong
                  ->after('service_package_name'); // Tentukan posisi kolom
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('service_details', function (Blueprint $table) {
            // Hapus kolom jika migrasi di-rollback
            $table->dropColumn('labor_cost_service');
        });
    }
}