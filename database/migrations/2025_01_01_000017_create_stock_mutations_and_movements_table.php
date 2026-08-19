<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStockMutationsAndMovementsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stock_mutations', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_mutasi', 50)->unique();
            $table->foreignId('barang_id')->constrained('barangs')->cascadeOnDelete();
            $table->foreignId('lokasi_asal_id')->constrained('lokasi')->cascadeOnDelete();
            $table->foreignId('lokasi_tujuan_id')->constrained('lokasi')->cascadeOnDelete();
            $table->foreignId('rak_asal_id')->nullable()->constrained('raks')->nullOnDelete();
            $table->foreignId('rak_tujuan_id')->nullable()->constrained('raks')->nullOnDelete();
            $table->unsignedInteger('jumlah');
            $table->integer('jumlah_diterima')->default(0);
            $table->enum('status', ['PENDING_APPROVAL', 'IN_TRANSIT', 'PARTIALLY_RECEIVED', 'COMPLETED', 'REJECTED'])->default('PENDING_APPROVAL');
            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_id')->constrained('barangs')->cascadeOnDelete();
            $table->foreignId('lokasi_id')->constrained('lokasi')->cascadeOnDelete();
            $table->foreignId('rak_id')->nullable()->constrained('raks')->nullOnDelete();
            $table->integer('jumlah');
            $table->integer('stok_sebelum');
            $table->integer('stok_sesudah');
            $table->text('keterangan')->nullable();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('referensi_type', 255)->index();
            $table->unsignedBigInteger('referensi_id');
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
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_mutations');
    }
}
