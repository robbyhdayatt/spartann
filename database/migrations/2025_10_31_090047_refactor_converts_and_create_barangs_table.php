<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // <-- Penting untuk DB::statement

class RefactorConvertsAndCreateBarangsTable extends Migration
{
    /**
     * Nama tabel lama dan baru
     */
    private $convertsOldName = 'converts';
    private $convertsNewName = 'converts_main';
    private $barangsTableName = 'barangs';
    private $viewName = 'converts'; // Nama view yang akan kita buat

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Buat tabel 'barangs' baru
        Schema::create($this->barangsTableName, function (Blueprint $table) {
            $table->id();
            $table->string('part_name');
            $table->string('merk')->nullable();
            // Jadikan part_code unik untuk relasi
            $table->string('part_code')->unique();
            $table->decimal('harga_modal', 15, 2)->default(0);
            $table->decimal('harga_jual', 15, 2)->default(0);
            $table->timestamps();
        });

        // 2. Modifikasi tabel 'converts'
        Schema::table($this->convertsOldName, function (Blueprint $table) {
            // Ganti nama 'part_code_input' menjadi 'part_code'
            $table->renameColumn('part_code_input', 'part_code');

            // Hapus kolom-kolom yang tidak diperlukan lagi
            $table->dropColumn([
                'original_part_code',
                'part_name',
                'merk',
                'harga_modal',
                'harga_jual'
            ]);
        });

        // 3. Ganti nama tabel 'converts' menjadi 'converts_main'
        Schema::rename($this->convertsOldName, $this->convertsNewName);

        // 4. Buat SQL Database VIEW
        // View ini akan menggabungkan 'converts_main' dan 'barangs'
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
                b.harga_modal,
                b.harga_jual,
                cm.created_at,
                cm.updated_at
            FROM
                {$this->convertsNewName} AS cm
            LEFT JOIN
                {$this->barangsTableName} AS b ON cm.part_code = b.part_code
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // 1. Hapus VIEW (dibalik urutannya)
        DB::statement("DROP VIEW IF EXISTS {$this->viewName}");

        // 2. Ganti nama tabel 'converts_main' kembali menjadi 'converts'
        Schema::rename($this->convertsNewName, $this->convertsOldName);

        // 3. Modifikasi tabel 'converts', tambahkan kembali kolom-kolom
        Schema::table($this->convertsOldName, function (Blueprint $table) {
            $table->renameColumn('part_code', 'part_code_input');

            // Tambahkan kembali kolom yang dihapus
            // Sesuaikan tipe data jika sebelumnya berbeda
            $table->string('original_part_code')->nullable()->after('id');
            $table->string('part_name')->nullable()->after('nama_job');
            $table->string('merk')->nullable()->after('part_name');
            $table->decimal('harga_modal', 15, 2)->default(0)->after('quantity');
            $table->decimal('harga_jual', 15, 2)->default(0)->after('harga_modal');
        });

        // 4. Hapus tabel 'barangs'
        Schema::dropIfExists($this->barangsTableName);
    }
}
