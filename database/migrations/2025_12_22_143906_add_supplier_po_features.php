<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSupplierPoFeatures extends Migration
{
    public function up()
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Membedakan Request Dealer vs PO ke Supplier
            $table->enum('po_type', ['dealer_request', 'supplier_po'])
                  ->default('dealer_request')
                  ->after('id');

            // Approval Kepala Gudang (Khusus PO Supplier)
            $table->foreignId('approved_by_head_id')->nullable()->constrained('users')->after('approved_by');
            $table->timestamp('approved_by_head_at')->nullable()->after('approved_at');
            
            // Kolom status perlu dimodifikasi di database secara manual jika menggunakan ENUM native MySQL,
            // atau biarkan logic aplikasi yang menanganinya jika string.
            // Di sini kita asumsikan kolom status string/enum cukup fleksibel atau ditangani di Model.
        });
    }

    public function down()
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['approved_by_head_id']);
            $table->dropColumn(['po_type', 'approved_by_head_id', 'approved_by_head_at']);
        });
    }
}