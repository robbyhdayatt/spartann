<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Jabatan;
use App\Models\Lokasi;
use App\Models\Dealer; // Pastikan model Dealer ada dan benar
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str; // Import Str facade

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
        // Ambil semua lokasi dealer beserta data dealer terkait (jika ada)
        // Menggunakan eager loading untuk efisiensi
        $dealerLokasi = Lokasi::where('tipe', 'DEALER')
                              ->with(['dealer' => function ($query) {
                                  // Asumsi relasi di model Lokasi bernama 'dealer'
                                  // dan foreign key di tabel dealers adalah 'kode_dealer'
                                  // yang berelasi dengan 'kode_lokasi' di tabel lokasi
                                  $query->select('kode_dealer', 'singkatan');
                              }])
                              ->get();

        // =================================================================
        // PENGGUNA LEVEL PUSAT (TIDAK TERIKAT LOKASI)
        // =================================================================
        User::create([
            'nik' => 'SA-001', 'username' => 'superadmin', 'nama' => 'Super Admin',
            'jabatan_id' => $jabatans['SA'], 'password' => Hash::make('password'), 'is_active' => true,
            'lokasi_id' => null, // Eksplisit set null jika tidak terikat lokasi
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

        // =================================================================
        // PENGGUNA LEVEL GUDANG PUSAT
        // =================================================================
        if ($gudangPusat) {
            // Gudang pusat mungkin tidak punya 'singkatan' di tabel dealers,
            // jadi kita gunakan kode_lokasi saja atau tentukan singkatan khusus
            $kodeLokasiPusat = Str::lower($gudangPusat->kode_lokasi); // contoh: 'gsp'
            $namaLokasiPusat = $gudangPusat->kode_lokasi; // contoh: 'GSP'

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
            // Ambil info dealer dari relasi yang sudah di-load
            $dealerInfo = $lokasi->dealer; // Asumsi relasi bernama 'dealer' di model Lokasi

            // Tentukan singkatan, gunakan kode_lokasi jika data dealer/singkatan tidak ada
            $singkatan = $dealerInfo ? $dealerInfo->singkatan : $lokasi->kode_lokasi;
            $usernameSuffix = Str::lower($singkatan); // Lowercase untuk username
            $namaSuffix = $singkatan; // Case asli untuk nama

            // Kepala Cabang
            User::create([
                'nik' => "KC-{$lokasi->kode_lokasi}-001", // NIK tetap pakai kode lokasi
                'username' => "kc_{$usernameSuffix}", // Username pakai singkatan lowercase
                'nama' => "Kepala Cabang {$namaSuffix}", // Nama pakai singkatan
                'jabatan_id' => $jabatans['KC'],
                'lokasi_id' => $lokasi->id, // Kolom foreign key baru
                'password' => Hash::make('password'),
                'is_active' => $lokasi->is_active, // Sesuaikan status user dengan status lokasi
            ]);

            // Admin Dealer
            User::create([
                'nik' => "AD-{$lokasi->kode_lokasi}-001",
                'username' => "admin_{$usernameSuffix}",
                'nama' => "Admin Dealer {$namaSuffix}",
                'jabatan_id' => $jabatans['AD'],
                'lokasi_id' => $lokasi->id, // Kolom foreign key baru
                'password' => Hash::make('password'),
                'is_active' => $lokasi->is_active,
            ]);

            // Sales Dealer
            User::create([
                'nik' => "SLS-{$lokasi->kode_lokasi}-001",
                'username' => "sales_{$usernameSuffix}",
                'nama' => "Sales {$namaSuffix}",
                'jabatan_id' => $jabatans['SLS'],
                'lokasi_id' => $lokasi->id, // Kolom foreign key baru
                'password' => Hash::make('password'),
                'is_active' => $lokasi->is_active,
            ]);

            // Counter Service Dealer
            User::create([
                'nik' => "CS-{$lokasi->kode_lokasi}-001",
                'username' => "counter_{$usernameSuffix}",
                'nama' => "Counter Service {$namaSuffix}",
                'jabatan_id' => $jabatans['CS'],
                'lokasi_id' => $lokasi->id, // Kolom foreign key baru
                'password' => Hash::make('password'),
                'is_active' => $lokasi->is_active,
            ]);

            // Kasir Dealer
            User::create([
                'nik' => "KSR-{$lokasi->kode_lokasi}-001",
                'username' => "kasir_{$usernameSuffix}",
                'nama' => "Kasir {$namaSuffix}",
                'jabatan_id' => $jabatans['KSR'],
                'lokasi_id' => $lokasi->id, // Kolom foreign key baru
                'password' => Hash::make('password'),
                'is_active' => $lokasi->is_active,
            ]);
        }
    }
}
