<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameGudangColumnsToLokasiInStockTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Ubah tabel stock_adjustments
        Schema::table('stock_adjustments', function (Blueprint $table) {
            // Opsional: Hapus foreign key jika ada
            // try { $table->dropForeign(['gudang_id']); } catch (\Exception $e) { $this->command->warn('FK drop failed for gudang_id in stock_adjustments'); }

            $table->renameColumn('gudang_id', 'lokasi_id');

            // Opsional: Tambah foreign key baru
            // $table->foreign('lokasi_id')->references('id')->on('lokasi')->onDelete('restrict');
        });

        // Ubah tabel stock_movements
        Schema::table('stock_movements', function (Blueprint $table) {
            // Opsional: Hapus foreign key jika ada
            // try { $table->dropForeign(['gudang_id']); } catch (\Exception $e) { $this->command->warn('FK drop failed for gudang_id in stock_movements'); }

            $table->renameColumn('gudang_id', 'lokasi_id');

            // Opsional: Tambah foreign key baru
            // $table->foreign('lokasi_id')->references('id')->on('lokasi')->onDelete('cascade'); // Atau sesuai kebutuhan
        });

        // Ubah tabel stock_mutations
        Schema::table('stock_mutations', function (Blueprint $table) {
            // Opsional: Hapus foreign key gudang_asal_id jika ada
            // try { $table->dropForeign(['gudang_asal_id']); } catch (\Exception $e) { $this->command->warn('FK drop failed for gudang_asal_id in stock_mutations'); }
            $table->renameColumn('gudang_asal_id', 'lokasi_asal_id');
            // Opsional: Tambah foreign key baru untuk lokasi_asal_id
            // $table->foreign('lokasi_asal_id')->references('id')->on('lokasi')->onDelete('restrict');

            // Opsional: Hapus foreign key gudang_tujuan_id jika ada
            // try { $table->dropForeign(['gudang_tujuan_id']); } catch (\Exception $e) { $this->command->warn('FK drop failed for gudang_tujuan_id in stock_mutations'); }
            $table->renameColumn('gudang_tujuan_id', 'lokasi_tujuan_id');
             // Opsional: Tambah foreign key baru untuk lokasi_tujuan_id
            // $table->foreign('lokasi_tujuan_id')->references('id')->on('lokasi')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Kembalikan tabel stock_adjustments
        Schema::table('stock_adjustments', function (Blueprint $table) {
            // Opsional: Hapus foreign key baru
            // try { $table->dropForeign(['lokasi_id']); } catch (\Exception $e) { $this->command->warn('FK drop failed for lokasi_id in stock_adjustments'); }

            $table->renameColumn('lokasi_id', 'gudang_id');

            // Opsional: Tambah foreign key lama
            // $table->foreign('gudang_id')->references('id')->on('lokasi')->onDelete('restrict');
        });

        // Kembalikan tabel stock_movements
        Schema::table('stock_movements', function (Blueprint $table) {
             // Opsional: Hapus foreign key baru
            // try { $table->dropForeign(['lokasi_id']); } catch (\Exception $e) { $this->command->warn('FK drop failed for lokasi_id in stock_movements'); }

            $table->renameColumn('lokasi_id', 'gudang_id');

            // Opsional: Tambah foreign key lama
            // $table->foreign('gudang_id')->references('id')->on('lokasi')->onDelete('cascade');
        });

        // Kembalikan tabel stock_mutations
        Schema::table('stock_mutations', function (Blueprint $table) {
             // Opsional: Hapus foreign key baru lokasi_asal_id
            // try { $table->dropForeign(['lokasi_asal_id']); } catch (\Exception $e) { $this->command->warn('FK drop failed for lokasi_asal_id in stock_mutations'); }
            $table->renameColumn('lokasi_asal_id', 'gudang_asal_id');
            // Opsional: Tambah foreign key lama gudang_asal_id
            // $table->foreign('gudang_asal_id')->references('id')->on('lokasi')->onDelete('restrict');

             // Opsional: Hapus foreign key baru lokasi_tujuan_id
            // try { $table->dropForeign(['lokasi_tujuan_id']); } catch (\Exception $e) { $this->command->warn('FK drop failed for lokasi_tujuan_id in stock_mutations'); }
            $table->renameColumn('lokasi_tujuan_id', 'gudang_tujuan_id');
            // Opsional: Tambah foreign key lama gudang_tujuan_id
            // $table->foreign('gudang_tujuan_id')->references('id')->on('lokasi')->onDelete('restrict');
        });
    }
}
