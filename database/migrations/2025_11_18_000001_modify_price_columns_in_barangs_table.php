<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ModifyPriceColumnsInBarangsTable extends Migration
{
    private $viewName = 'converts'; // Nama view yang terpengaruh

    public function up()
    {
        // 1. Hapus View lama dulu karena view ini SELECT kolom yang akan kita rename
        DB::statement("DROP VIEW IF EXISTS {$this->viewName}");

        // 2. Rename dan Tambah Kolom di tabel 'barangs'
        Schema::table('barangs', function (Blueprint $table) {
            // Rename
            $table->renameColumn('harga_modal', 'selling_out');
            $table->renameColumn('harga_jual', 'retail');

            // Tambah kolom baru
            $table->decimal('selling_in', 15, 2)->default(0)->after('part_code');
        });

        // 3. Buat Ulang View dengan nama kolom baru
        DB::statement("
            CREATE VIEW {$this->viewName} AS
            SELECT
                cm.id,
                cm.nama_job,
                cm.keterangan,
                cm.quantity,
                cm.part_code,
                b.part_name,
                b.merk,
                b.selling_in,
                b.selling_out,
                b.retail,
                cm.created_at,
                cm.updated_at
            FROM
                converts_main AS cm
            LEFT JOIN
                barangs AS b ON cm.part_code = b.part_code
        ");
    }

    public function down()
    {
        DB::statement("DROP VIEW IF EXISTS {$this->viewName}");

        Schema::table('barangs', function (Blueprint $table) {
            $table->dropColumn('selling_in');
            $table->renameColumn('selling_out', 'harga_modal');
            $table->renameColumn('retail', 'harga_jual');
        });

        // Re-create view old version (simplified for down)
        // ... (optional logic for down)
    }
}
