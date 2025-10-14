<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    public function boot()
    {
        $this->registerPolicies();

        /**
         * Super Admin memiliki akses tak terbatas ke semua fitur.
         */
        Gate::before(fn(User $user) => $user->hasRole('SA') ? true : null);

        // =================================================================
        // DEFINISI GATE BERDASARKAN PERAN (ROLE-BASED CHECKS)
        // =================================================================
        Gate::define('is-super-admin', fn(User $user) => $user->hasRole('SA'));
        Gate::define('is-pic', fn(User $user) => $user->hasRole('PIC'));
        Gate::define('is-manager', fn(User $user) => $user->hasRole('MA'));

        // Cek apakah pengguna adalah staf di Gudang Pusat
        Gate::define('is-pusat-staff', fn(User $user) => in_array($user->jabatan->singkatan, ['KG', 'AG', 'SLS', 'KSR']));
        // Cek apakah pengguna adalah staf di Dealer
        Gate::define('is-dealer-staff', fn(User $user) => in_array($user->jabatan->singkatan, ['KC', 'AD', 'CS']));


        // =================================================================
        // DEFINISI GATE BERDASARKAN TUGAS (PERMISSION-BASED)
        // =================================================================

        // --- PENGATURAN & MASTER DATA ---
        Gate::define('manage-users', fn(User $user) => $user->hasRole('SA')); // Hanya Super Admin
        Gate::define('manage-locations', fn(User $user) => $user->hasRole(['SA', 'PIC']));
        Gate::define('view-master-data', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA']));

        // --- PEMBELIAN (HANYA DI PUSAT) ---
        Gate::define('access-po-module', fn(User $user) => $user->hasRole(['KG', 'AG']));
        Gate::define('create-po', fn(User $user) => $user->hasRole('AG'));
        Gate::define('approve-po', function (User $user, $purchaseOrder) {
            return $user->hasRole('KG') && $user->lokasi->tipe === 'PUSAT' && $user->gudang_id === $purchaseOrder->gudang_id;
        });

        // --- OPERASIONAL GUDANG & DEALER ---
        Gate::define('perform-warehouse-ops', fn(User $user) => $user->hasRole(['AG', 'AD']));
        Gate::define('create-stock-transaction', fn(User $user) => $user->hasRole(['AG', 'AD']));
        Gate::define('approve-stock-transaction', function (User $user, $transaction) {
            $lokasiId = $transaction->gudang_id ?? $transaction->gudang_asal_id;
            // Kepala Gudang hanya bisa approve transaksi di lokasinya (Gudang Pusat)
            if ($user->hasRole('KG')) {
                return $user->gudang_id === $lokasiId;
            }
            // Kepala Cabang hanya bisa approve transaksi di lokasinya (Dealer)
            if ($user->hasRole('KC')) {
                return $user->gudang_id === $lokasiId;
            }
            return false;
        });

        // --- PENJUALAN ---
        Gate::define('access-sales-module', fn(User $user) => $user->hasRole(['SLS', 'KSR', 'CS']));

        // --- LAPORAN & MARKETING ---
        Gate::define('view-reports', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA', 'KG', 'KC']));
        Gate::define('manage-marketing', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA']));

        // --- AKSES KHUSUS KEPALA CABANG (READ-ONLY) ---
        // Gate ini akan digunakan untuk menyembunyikan tombol aksi di view.
        Gate::define('is-read-only', fn(User $user) => $user->hasRole('KC'));
    }
}
