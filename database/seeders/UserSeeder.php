<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Jabatan;
use App\Models\Lokasi;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Schema::disableForeignKeyConstraints();
        User::query()->truncate();
        Schema::enableForeignKeyConstraints();

        // Ambil ID Jabatan untuk referensi
        $jabatans = Jabatan::pluck('id', 'singkatan');
        
        // Ambil Lokasi Spesifik berdasarkan Kode Baru
        $kantorPusat = Lokasi::where('kode_lokasi', 'KANTOR PUSAT')->first();
        $gudangPusat = Lokasi::where('kode_lokasi', 'GUDANG PART')->first();
        
        // Ambil Semua Dealer
        $dealerLokasi = Lokasi::where('tipe', 'DEALER')->get();

        // Default Password
        $password = Hash::make('password');

        // =================================================================
        // 1. PENGGUNA GLOBAL (Tanpa Lokasi)
        // =================================================================
        User::create([
            'nik' => 'SA-001', 'username' => 'superadmin', 'nama' => 'Super Admin',
            'jabatan_id' => $jabatans['SA'], 'password' => $password, 'is_active' => true,
            'lokasi_id' => null, 
        ]);

        User::create([
            'nik' => 'PIC-001', 'username' => 'pic', 'nama' => 'PIC',
            'jabatan_id' => $jabatans['PIC'], 'password' => $password, 'is_active' => true,
            'lokasi_id' => null,
        ]);

        // =================================================================
        // 2. PENGGUNA DI MAIN DEALER (KANTOR PUSAT)
        // =================================================================
        if ($kantorPusat) {
            // Area Service Development
            User::create([
                'nik' => 'ASD-001', 'username' => 'asd_pusat', 'nama' => 'Staff ASD',
                'jabatan_id' => $jabatans['ASD'], 'password' => $password, 'is_active' => true,
                'lokasi_id' => $kantorPusat->id, 
            ]);

            // Inventory MD Shop (Pengganti Service MD)
            User::create([
                'nik' => 'IMS-001', 'username' => 'inventory_md', 'nama' => 'Staff Inventory MD',
                'jabatan_id' => $jabatans['IMS'], 'password' => $password, 'is_active' => true,
                'lokasi_id' => $kantorPusat->id, 
            ]);

            // Accounting MD
            User::create([
                'nik' => 'ACC-001', 'username' => 'accounting_md', 'nama' => 'Staff Accounting',
                'jabatan_id' => $jabatans['ACC'], 'password' => $password, 'is_active' => true,
                'lokasi_id' => $kantorPusat->id, 
            ]);
        }

        // =================================================================
        // 3. PENGGUNA DI GUDANG PUSAT (GUDANG PART)
        // =================================================================
        if ($gudangPusat) {
            User::create([
                'nik' => "KG-PUSAT", 'username' => "kepala_gudang", 'nama' => "Kepala Gudang",
                'jabatan_id' => $jabatans['KG'], 'lokasi_id' => $gudangPusat->id, 'password' => $password, 'is_active' => true,
            ]);

            User::create([
                'nik' => "AG-PUSAT", 'username' => "admin_gudang", 'nama' => "Admin Gudang",
                'jabatan_id' => $jabatans['AG'], 'lokasi_id' => $gudangPusat->id, 'password' => $password, 'is_active' => true,
            ]);
        
        }

        // =================================================================
        // 4. PENGGUNA DI SETIAP DEALER CABANG
        // =================================================================
        foreach ($dealerLokasi as $lokasi) {
            $suffix = Str::lower($lokasi->singkatan); 
            $namaSuffix = $lokasi->singkatan; 

            // Kepala Cabang
            User::create([
                'nik' => "KC-{$lokasi->singkatan}",
                'username' => "kc_{$suffix}",
                'nama' => "Kepala Cabang {$namaSuffix}",
                'jabatan_id' => $jabatans['KC'],
                'lokasi_id' => $lokasi->id,
                'password' => $password,
                'is_active' => $lokasi->is_active, 
            ]);

            // Part Counter
            User::create([
                'nik' => "PC-{$lokasi->singkatan}",
                'username' => "pc_{$suffix}",
                'nama' => "Part Counter {$namaSuffix}",
                'jabatan_id' => $jabatans['PC'],
                'lokasi_id' => $lokasi->id, 
                'password' => $password,
                'is_active' => $lokasi->is_active,
            ]);

            // Kasir
            User::create([
                'nik' => "KSR-{$lokasi->singkatan}",
                'username' => "kasir_{$suffix}",
                'nama' => "Kasir {$namaSuffix}",
                'jabatan_id' => $jabatans['KSR'],
                'lokasi_id' => $lokasi->id, 
                'password' => $password,
                'is_active' => $lokasi->is_active,
            ]);
            
        }
    }
}