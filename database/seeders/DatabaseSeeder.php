<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            // Urutan ini penting
            JabatanSeeder::class,
            DealerSeeder::class,
            LokasiSeeder::class, // LokasiSeeder harus dijalankan sebelum UserSeeder
            RakSeeder::class,    // RakSeeder sekarang akan berjalan setelah lokasi dibuat
            BrandSeeder::class,
            CategorySeeder::class,
            SupplierSeeder::class,
            KonsumenSeeder::class,
            UserSeeder::class, // Pastikan UserSeeder juga sudah disesuaikan jika perlu
        ]);
    }
}
