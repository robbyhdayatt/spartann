<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyInventoryBatchesAndDropInventoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Ubah nama kolom di tabel inventory_batches
        Schema::table('inventory_batches', function (Blueprint $table) {
            // Opsional: Hapus foreign key constraint jika ada
            // Pastikan nama constraint 'inventory_batches_gudang_id_foreign' sudah benar
            // try {
            //     $table->dropForeign(['gudang_id']);
            // } catch (\Exception $e) {
            //     // Handle error jika constraint tidak ada atau nama berbeda
            //     $this->command->warn('Could not drop foreign key for gudang_id in inventory_batches: ' . $e->getMessage());
            // }

            $table->renameColumn('gudang_id', 'lokasi_id');

            // Opsional: Tambahkan kembali foreign key constraint dengan nama baru
            // $table->foreign('lokasi_id')
            //       ->references('id')
            //       ->on('lokasi') // Pastikan merujuk ke tabel 'lokasi'
            //       ->onDelete('restrict'); // atau 'set null', 'cascade'
        });

        // 2. Hapus tabel inventories
        Schema::dropIfExists('inventories');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // 1. Buat kembali tabel inventories (sesuaikan strukturnya dengan migrasi sebelumnya)
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            // Tambahkan kolom-kolom lain yang sebelumnya ada di tabel inventories
            // Contoh:
            // $table->foreignId('part_id')->constrained('parts');
            // $table->foreignId('lokasi_id')->constrained('lokasi'); // atau gudang_id jika rollback migrasi lain
            // $table->integer('quantity');
            // ... kolom lainnya ...
            $table->timestamps();
            $table->index(['part_id', 'lokasi_id']); // Contoh index
        });

        // 2. Kembalikan nama kolom di tabel inventory_batches
        Schema::table('inventory_batches', function (Blueprint $table) {
             // Opsional: Hapus foreign key constraint baru
            // try {
            //    $table->dropForeign(['lokasi_id']);
            // } catch (\Exception $e) {
            //     $this->command->warn('Could not drop foreign key for lokasi_id in inventory_batches: ' . $e->getMessage());
            // }

            $table->renameColumn('lokasi_id', 'gudang_id');

            // Opsional: Tambahkan kembali foreign key constraint lama
            // $table->foreign('gudang_id')
            //       ->references('id')
            //       ->on('lokasi') // atau 'gudangs' tergantung migrasi
            //       ->onDelete('restrict');
        });
    }
}
