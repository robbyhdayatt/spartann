<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Jabatan;
use App\Models\Lokasi;
use App\Models\Dealer; // Import model Dealer
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

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

        // Ambil data yang dibutuhkan untuk relasi
        $jabatans = Jabatan::pluck('id', 'singkatan');
        $gudangPusat = Lokasi::where('tipe', 'PUSAT')->first();
        $dealers = Lokasi::where('tipe', 'DEALER')->get();

        // =================================================================
        // PENGGUNA LEVEL PUSAT (TIDAK TERIKAT LOKASI)
        // =================================================================
        User::create([
            'nik' => 'SA-001', 'username' => 'superadmin', 'nama' => 'Super Admin',
            'jabatan_id' => $jabatans['SA'], 'password' => Hash::make('password'), 'is_active' => true,
        ]);

        User::create([
            'nik' => 'PIC-001', 'username' => 'pic', 'nama' => 'PIC',
            'jabatan_id' => $jabatans['PIC'], 'password' => Hash::make('password'), 'is_active' => true,
        ]);

        User::create([
            'nik' => 'MA-001', 'username' => 'manajer', 'nama' => 'Manajer Area',
            'jabatan_id' => $jabatans['MA'], 'password' => Hash::make('password'), 'is_active' => true,
        ]);

        // =================================================================
        // PENGGUNA LEVEL GUDANG PUSAT
        // =================================================================
        if ($gudangPusat) {
            $kodeLokasi = strtolower($gudangPusat->kode_gudang);

            User::create([
                'nik' => "KG-{$gudangPusat->kode_gudang}-001", 'username' => "kg_{$kodeLokasi}", 'nama' => "Kepala Gudang Pusat",
                'jabatan_id' => $jabatans['KG'], 'gudang_id' => $gudangPusat->id, 'password' => Hash::make('password'), 'is_active' => true,
            ]);

            User::create([
                'nik' => "AG-{$gudangPusat->kode_gudang}-001", 'username' => "admin_{$kodeLokasi}", 'nama' => "Admin Gudang Pusat",
                'jabatan_id' => $jabatans['AG'], 'gudang_id' => $gudangPusat->id, 'password' => Hash::make('password'), 'is_active' => true,
            ]);

            User::create([
                'nik' => "SLS-{$gudangPusat->kode_gudang}-001", 'username' => "sales_{$kodeLokasi}", 'nama' => "Sales Pusat",
                'jabatan_id' => $jabatans['SLS'], 'gudang_id' => $gudangPusat->id, 'password' => Hash::make('password'), 'is_active' => true,
            ]);

            User::create([
                'nik' => "KSR-{$gudangPusat->kode_gudang}-001", 'username' => "kasir_{$kodeLokasi}", 'nama' => "Kasir Pusat",
                'jabatan_id' => $jabatans['KSR'], 'gudang_id' => $gudangPusat->id, 'password' => Hash::make('password'), 'is_active' => true,
            ]);
        }
        
        // =================================================================
        // === PENGGUNA LEVEL DEALER (LOOPING UNTUK SETIAP DEALER) ===
        // =================================================================
        foreach ($dealers as $dealer) {
            // Ambil singkatan dari tabel dealers untuk username yang lebih pendek
            $dealerInfo = Dealer::where('kode_dealer', $dealer->kode_gudang)->first();
            $kodeLokasi = $dealerInfo ? strtolower($dealerInfo->singkatan) : strtolower($dealer->kode_gudang);
            $namaLokasi = $dealerInfo ? $dealerInfo->singkatan : $dealer->kode_gudang;

            // Kepala Cabang
            User::create([
                'nik' => "KC-{$dealer->kode_gudang}-001", 'username' => "kc_{$kodeLokasi}", 'nama' => "Kepala Cabang {$namaLokasi}",
                'jabatan_id' => $jabatans['KC'], 'gudang_id' => $dealer->id, 'password' => Hash::make('password'), 'is_active' => true,
            ]);

            // Admin Dealer
            User::create([
                'nik' => "AD-{$dealer->kode_gudang}-001", 'username' => "admin_{$kodeLokasi}", 'nama' => "Admin Dealer {$namaLokasi}",
                'jabatan_id' => $jabatans['AD'], 'gudang_id' => $dealer->id, 'password' => Hash::make('password'), 'is_active' => true,
            ]);
            
            // Sales Dealer
            User::create([
                'nik' => "SLS-{$dealer->kode_gudang}-001", 'username' => "sales_{$kodeLokasi}", 'nama' => "Sales {$namaLokasi}",
                'jabatan_id' => $jabatans['SLS'], 'gudang_id' => $dealer->id, 'password' => Hash::make('password'), 'is_active' => true,
            ]);

            // --- PERUBAHAN DI SINI ---
            // Counter Service Dealer (sebelumnya Counter Sales)
            User::create([
                'nik' => "CS-{$dealer->kode_gudang}-001", 'username' => "counter_{$kodeLokasi}", 'nama' => "Counter Service {$namaLokasi}",
                'jabatan_id' => $jabatans['CS'], 'gudang_id' => $dealer->id, 'password' => Hash::make('password'), 'is_active' => true,
            ]);
            
            // Kasir Dealer
            User::create([
                'nik' => "KSR-{$dealer->kode_gudang}-001", 'username' => "kasir_{$kodeLokasi}", 'nama' => "Kasir {$namaLokasi}",
                'jabatan_id' => $jabatans['KSR'], 'gudang_id' => $dealer->id, 'password' => Hash::make('password'), 'is_active' => true,
            ]);
        }
    }
}

