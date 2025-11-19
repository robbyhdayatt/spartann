<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdatePoForDistribution extends Migration
{
    public function up()
    {
        // 1. Modifikasi Header PO
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Group ID untuk melacak 1 request besar yang dipecah
            $table->string('request_group_id', 50)->nullable()->after('nomor_po')->index();

            // Sumber barang (Gudang Pusat) - Pengganti Supplier jika Internal Transfer
            $table->foreignId('sumber_lokasi_id')->nullable()->after('supplier_id')->constrained('lokasi');

            // Supplier jadi nullable (karena bisa dari internal)
            $table->unsignedBigInteger('supplier_id')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['request_group_id', 'sumber_lokasi_id']);
            // $table->unsignedBigInteger('supplier_id')->nullable(false)->change(); // Hati-hati saat rollback
        });
    }
}
