<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServicesAndServiceDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('yss', 50)->nullable();
            $table->string('dealer_code', 50)->nullable();
            $table->foreignId('lokasi_id')->nullable()->constrained('lokasi')->nullOnDelete();
            $table->string('point', 50)->nullable();
            $table->date('reg_date')->nullable();
            $table->string('service_order', 100)->nullable();
            $table->string('plate_no', 20)->nullable();
            $table->string('work_order_no', 50)->nullable();
            $table->string('work_order_status', 50)->nullable();
            $table->string('invoice_no', 50);
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
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();

            // Constraints & Performance Indexes
            $table->unique(['invoice_no', 'dealer_code'], 'services_invoice_no_unique');
            $table->index('reg_date', 'idx_services_reg_date');
            $table->index('created_at', 'idx_services_created_at');
            $table->index('dealer_code', 'idx_services_dealer_code');
            $table->index('service_order', 'idx_services_service_order');
            $table->index('payment_type', 'idx_services_payment_type');
            $table->index(['dealer_code', 'reg_date'], 'idx_services_dealer_reg_date');
            $table->index(['lokasi_id', 'reg_date'], 'idx_services_lokasi_reg_date');
            $table->index(['lokasi_id', 'created_at'], 'idx_services_lokasi_created_at');
        });

        Schema::create('service_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('barang_id')->nullable()->constrained('barangs')->nullOnDelete();
            $table->enum('item_category', ['JASA', 'PART', 'OLI', 'LAINNYA']);
            $table->string('service_category_code', 50)->nullable();
            $table->string('service_package_name', 255)->nullable();
            $table->decimal('labor_cost_service', 15, 2)->default(0.00);
            $table->string('item_code', 255)->nullable();
            $table->string('item_name', 255)->nullable();
            $table->integer('quantity');
            $table->decimal('price', 15, 2);
            $table->decimal('cost_price', 15, 2)->default(0.00);
            $table->timestamps();

            // Performance Indexes
            $table->index('item_category', 'idx_sd_item_category');
            $table->index('service_category_code', 'idx_sd_category_code');
            $table->index('item_code', 'idx_sd_item_code');
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
