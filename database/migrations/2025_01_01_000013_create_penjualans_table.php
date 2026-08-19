<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePenjualansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('penjualans', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_faktur', 50)->unique();
            $table->foreignId('konsumen_id')->constrained('konsumens')->cascadeOnDelete();
            $table->foreignId('lokasi_id')->constrained('lokasi')->cascadeOnDelete();
            $table->foreignId('sales_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 255)->default('COMPLETED');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('tanggal_jual');
            $table->decimal('total_harga', 15, 2)->default(0.00);
            $table->decimal('subtotal', 15, 2)->default(0.00);
            $table->decimal('diskon', 15, 2)->default(0.00);
            $table->string('keterangan_diskon', 255)->nullable();
            $table->decimal('total_diskon', 15, 2)->default(0.00);
            $table->decimal('pajak', 15, 2)->default(0.00);
            $table->timestamps();
        });

        Schema::create('penjualan_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penjualan_id')->constrained('penjualans')->cascadeOnDelete();
            $table->foreignId('barang_id')->nullable()->constrained('barangs')->nullOnDelete();
            $table->foreignId('rak_id')->nullable()->constrained('raks')->nullOnDelete();
            $table->integer('qty_jual');
            $table->decimal('harga_modal', 15, 2)->default(0.00);
            $table->integer('qty_diretur')->default(0);
            $table->decimal('harga_jual', 15, 2);
            $table->decimal('subtotal', 15, 2);
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
        Schema::dropIfExists('penjualan_details');
        Schema::dropIfExists('penjualans');
    }
}
