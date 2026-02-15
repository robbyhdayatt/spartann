<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropMarketingAndIncentiveTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Urutan drop penting untuk menghindari error foreign key constraint
        Schema::dropIfExists('incentives');
        Schema::dropIfExists('sales_targets');
        Schema::dropIfExists('campaign_konsumen');
        Schema::dropIfExists('campaign_part');
        Schema::dropIfExists('campaign_supplier');
        Schema::dropIfExists('campaign_category_konsumen');
        Schema::dropIfExists('campaign_categories');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('customer_discount_category_konsumen');
        Schema::dropIfExists('customer_discount_categories');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Karena ini penghapusan total, method down bisa dikosongkan 
        // atau Anda harus mendefinisikan ulang semua tabel jika ingin bisa di-rollback.
    }
}