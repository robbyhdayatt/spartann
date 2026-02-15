<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveTaxFromSalesReturns extends Migration
{
    public function up()
    {
        Schema::table('sales_returns', function (Blueprint $table) {
            $table->dropColumn(['pajak', 'subtotal']);
        });
    }

    public function down()
    {
        Schema::table('sales_returns', function (Blueprint $table) {
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('pajak', 15, 2)->default(0);
        });
    }
}