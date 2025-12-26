<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateStatusEnumInReceivingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Menambahkan 'PARTIAL_CLOSED' ke dalam pilihan ENUM status
        DB::statement("ALTER TABLE receivings MODIFY COLUMN status ENUM('PENDING_QC', 'PENDING_PUTAWAY', 'COMPLETED', 'PARTIAL_CLOSED') NOT NULL DEFAULT 'PENDING_QC'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Mengembalikan ke pilihan ENUM sebelumnya (PERINGATAN: Data PARTIAL_CLOSED bisa hilang/error jika di-rollback)
        DB::statement("ALTER TABLE receivings MODIFY COLUMN status ENUM('PENDING_QC', 'PENDING_PUTAWAY', 'COMPLETED') NOT NULL DEFAULT 'PENDING_QC'");
    }
}