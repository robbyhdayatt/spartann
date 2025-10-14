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
            GudangSeeder::class, // Ini akan membuat 1 Gudang Pusat di tabel 'lokasi'
            DealerSeeder::class, // Ini akan mengisi tabel 'dealers'
            RakSeeder::class,    // RakSeeder sekarang akan berjalan setelah lokasi dibuat
            BrandSeeder::class,
            CategorySeeder::class,
            SupplierSeeder::class,
            KonsumenSeeder::class,
            UserSeeder::class, // Pastikan UserSeeder juga sudah disesuaikan jika perlu
        ]);
    }
}
