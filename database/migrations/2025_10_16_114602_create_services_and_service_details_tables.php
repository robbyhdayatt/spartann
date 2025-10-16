<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServicesAndServiceDetailsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Tabel untuk menyimpan data header dari Service
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('yss', 50)->nullable();
            $table->string('dealer_code', 50)->nullable();
            $table->string('point', 50)->nullable();
            $table->date('reg_date')->nullable();
            $table->string('service_order', 100)->nullable();
            $table->string('plate_no', 20)->nullable();
            $table->string('work_order_no', 50)->nullable();
            $table->string('work_order_status', 50)->nullable();
            $table->string('invoice_no', 50)->unique();
            $table->string('customer_name', 255)->nullable();
            $table->string('customer_ktp', 50)->nullable();
            $table->string('customer_npwp_no', 50)->nullable();
            $table->string('customer_npwp_name', 255)->nullable();
            $table->string('customer_phone', 25)->nullable();
            $table->string('mc_brand', 50)->nullable();
            $table->string('mc_model_name', 100)->nullable();
            $table->string('mc_frame_no', 100)->nullable();
            $table->string('technician_name', 255)->nullable();
            $table->string('payment_type', 50)->nullable();
            $table->string('transaction_code', 100)->nullable();
            $table->decimal('e_payment_amount', 15, 2)->default(0.00);
            $table->decimal('cash_amount', 15, 2)->default(0.00);
            $table->decimal('debit_amount', 15, 2)->default(0.00);
            $table->decimal('total_down_payment', 15, 2)->default(0.00);
            $table->decimal('total_labor', 15, 2)->default(0.00);
            $table->decimal('total_part_service', 15, 2)->default(0.00);
            $table->decimal('total_oil_service', 15, 2)->default(0.00);
            $table->decimal('total_retail_parts', 15, 2)->default(0.00);
            $table->decimal('total_retail_oil', 15, 2)->default(0.00);
            $table->decimal('total_amount', 15, 2)->default(0.00);
            $table->decimal('benefit_amount', 15, 2)->default(0.00);
            $table->decimal('total_payment', 15, 2)->default(0.00);
            $table->decimal('balance', 15, 2)->default(0.00);
            $table->timestamps();
        });

        // Tabel untuk menyimpan data detail/item dari setiap transaksi di Service
        Schema::create('service_details', function (Blueprint $table) {
            $table->id();
            // PERBAIKAN: Nama kolom foreign key disesuaikan menjadi 'service_id'
            $table->unsignedBigInteger('service_id'); 
            $table->enum('item_category', ['JASA', 'PART', 'OLI', 'LAINNYA']);
            $table->string('service_category_code', 50)->nullable();
            $table->string('service_package_name', 255)->nullable();
            $table->string('item_code', 100)->nullable();
            $table->string('item_name');
            $table->integer('quantity');
            $table->decimal('price', 15, 2);
            $table->timestamps();

            // PERBAIKAN: Foreign key constraint menunjuk ke tabel 'services'
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('service_details');
        Schema::dropIfExists('services');
    }
}