<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Jabatan;
use App\Models\Lokasi;
use App\Models\Dealer; 
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
        // Nonaktifkan foreign key checks untuk truncate
        Schema::disableForeignKeyConstraints();
        User::query()->truncate();
        Schema::enableForeignKeyConstraints();

        // Ambil ID Jabatan berdasarkan singkatan
        // Pastikan JabatanSeeder sudah dijalankan sehingga 'PC' sudah ada
        $jabatans = Jabatan::pluck('id', 'singkatan');
        
        $gudangPusat = Lokasi::where('tipe', 'PUSAT')->first();
        $dealerLokasi = Lokasi::where('tipe', 'DEALER')
                              ->with(['dealer' => function ($query) {
                                  $query->select('kode_dealer', 'singkatan');
                              }])
                              ->get();

        // =================================================================
        // PENGGUNA LEVEL PUSAT (TIDAK TERIKAT LOKASI)
        // =================================================================
        User::create([
            'nik' => 'SA-001', 'username' => 'superadmin', 'nama' => 'Super Admin',
            'jabatan_id' => $jabatans['SA'], 'password' => Hash::make('password'), 'is_active' => true,
            'lokasi_id' => null, 
        ]);

        User::create([
            'nik' => 'PIC-001', 'username' => 'pic', 'nama' => 'PIC',
            'jabatan_id' => $jabatans['PIC'], 'password' => Hash::make('password'), 'is_active' => true,
            'lokasi_id' => null,
        ]);

        User::create([
            'nik' => 'MA-001', 'username' => 'manajer', 'nama' => 'Manajer Area',
            'jabatan_id' => $jabatans['MA'], 'password' => Hash::make('password'), 'is_active' => true,
            'lokasi_id' => null,
        ]);

        User::create([
            'nik' => 'ASD-001',
            'username' => 'asd',
            'nama' => 'Area Service Development',
            'jabatan_id' => $jabatans['ASD'],   
            'password' => Hash::make('password'),
            'is_active' => true,
            'lokasi_id' => null, 
        ]);

        User::create([
            'nik' => 'SMD-001',
            'username' => 'servicemd',
            'nama' => 'Service MD',
            'jabatan_id' => $jabatans['SMD'],
            'password' => Hash::make('password'),
            'is_active' => true,
            'lokasi_id' => null, 
        ]);

        User::create([
            'nik' => 'ACC-001',
            'username' => 'accounting',
            'nama' => 'Accounting MD',
            'jabatan_id' => $jabatans['ACC'],
            'password' => Hash::make('password'),
            'is_active' => true,
            'lokasi_id' => null, 
        ]);

        // =================================================================
        // PENGGUNA LEVEL GUDANG PUSAT
        // =================================================================
        if ($gudangPusat) {
            $kodeLokasiPusat = Str::lower($gudangPusat->kode_lokasi); 
            
            User::create([
                'nik' => "KG-{$gudangPusat->kode_lokasi}-001", 'username' => "kg_{$kodeLokasiPusat}", 'nama' => "Kepala Gudang Pusat",
                'jabatan_id' => $jabatans['KG'], 'lokasi_id' => $gudangPusat->id, 'password' => Hash::make('password'), 'is_active' => true,
            ]);

            User::create([
                'nik' => "AG-{$gudangPusat->kode_lokasi}-001", 'username' => "admin_{$kodeLokasiPusat}", 'nama' => "Admin Gudang Pusat",
                'jabatan_id' => $jabatans['AG'], 'lokasi_id' => $gudangPusat->id, 'password' => Hash::make('password'), 'is_active' => true,
            ]);

            User::create([
                'nik' => "SLS-{$gudangPusat->kode_lokasi}-001", 'username' => "sales_{$kodeLokasiPusat}", 'nama' => "Sales Pusat",
                'jabatan_id' => $jabatans['SLS'], 'lokasi_id' => $gudangPusat->id, 'password' => Hash::make('password'), 'is_active' => true,
            ]);

            User::create([
                'nik' => "KSR-{$gudangPusat->kode_lokasi}-001", 'username' => "kasir_{$kodeLokasiPusat}", 'nama' => "Kasir Pusat",
                'jabatan_id' => $jabatans['KSR'], 'lokasi_id' => $gudangPusat->id, 'password' => Hash::make('password'), 'is_active' => true,
            ]);
        }

        // =================================================================
        // === PENGGUNA LEVEL DEALER (LOOPING UNTUK SETIAP DEALER LOKASI) ===
        // =================================================================
        foreach ($dealerLokasi as $lokasi) {
            $dealerInfo = $lokasi->dealer; 
            $singkatan = $dealerInfo ? $dealerInfo->singkatan : $lokasi->kode_lokasi;
            $usernameSuffix = Str::lower($singkatan); 
            $namaSuffix = $singkatan; 

            // Kepala Cabang
            User::create([
                'nik' => "KC-{$lokasi->kode_lokasi}-001",
                'username' => "kc_{$usernameSuffix}",
                'nama' => "Kepala Cabang {$namaSuffix}",
                'jabatan_id' => $jabatans['KC'],
                'lokasi_id' => $lokasi->id,
                'password' => Hash::make('password'),
                'is_active' => $lokasi->is_active, 
            ]);

            // Admin Dealer
            User::create([
                'nik' => "AD-{$lokasi->kode_lokasi}-001",
                'username' => "admin_{$usernameSuffix}",
                'nama' => "Admin Dealer {$namaSuffix}",
                'jabatan_id' => $jabatans['AD'],
                'lokasi_id' => $lokasi->id, 
                'password' => Hash::make('password'),
                'is_active' => $lokasi->is_active,
            ]);

            // Sales Dealer
            User::create([
                'nik' => "SLS-{$lokasi->kode_lokasi}-001",
                'username' => "sales_{$usernameSuffix}",
                'nama' => "Sales {$namaSuffix}",
                'jabatan_id' => $jabatans['SLS'],
                'lokasi_id' => $lokasi->id, 
                'password' => Hash::make('password'),
                'is_active' => $lokasi->is_active,
            ]);

            // PERUBAHAN: Part Counter (PC) - Menggantikan CS
            User::create([
                'nik' => "PC-{$lokasi->kode_lokasi}-001", // NIK diubah jadi PC
                'username' => "partcounter_{$usernameSuffix}", // Username diubah
                'nama' => "Part Counter {$namaSuffix}", // Nama diubah
                'jabatan_id' => $jabatans['PC'], // ID Jabatan PC
                'lokasi_id' => $lokasi->id, 
                'password' => Hash::make('password'),
                'is_active' => $lokasi->is_active,
            ]);

            // Kasir Dealer
            User::create([
                'nik' => "KSR-{$lokasi->kode_lokasi}-001",
                'username' => "kasir_{$usernameSuffix}",
                'nama' => "Kasir {$namaSuffix}",
                'jabatan_id' => $jabatans['KSR'],
                'lokasi_id' => $lokasi->id, 
                'password' => Hash::make('password'),
                'is_active' => $lokasi->is_active,
            ]);
        }
    }
}