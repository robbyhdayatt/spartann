<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeysToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('lokasi_id')->nullable()->after('password')->constrained('lokasi')->nullOnDelete();
            $table->foreignId('jabatan_id')->nullable()->after('lokasi_id')->constrained('jabatans')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['lokasi_id']);
            $table->dropForeign(['jabatan_id']);
            $table->dropColumn(['lokasi_id', 'jabatan_id']);
        });
    }
}
