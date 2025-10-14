<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Jabatan;
use App\Models\Lokasi;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class UserSeeder extends Seeder
{
    public function run()
    {
        Schema::disableForeignKeyConstraints();
        User::query()->truncate();
        Schema::enableForeignKeyConstraints();

        $jabatans = Jabatan::pluck('id', 'singkatan');
        $gudangPusat = Lokasi::where('tipe', 'PUSAT')->first();

        // === PENGGUNA LEVEL PUSAT (TIDAK TERIKAT LOKASI) ===
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

        // === PENGGUNA LEVEL GUDANG PUSAT ===
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
    }
}
