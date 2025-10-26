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

        Gate::before(fn(User $user) => $user->hasRole('SA') ? true : null);

        // =================================================================
        // DEFINISI GATE BERDASARKAN PERAN (ROLE-BASED CHECKS)
        // =================================================================
        Gate::define('is-super-admin', fn(User $user) => $user->hasRole('SA'));
        Gate::define('is-pic', fn(User $user) => $user->hasRole('PIC'));
        Gate::define('is-manager', fn(User $user) => $user->hasRole('MA'));

        // Staf operasional di Gudang Pusat
        Gate::define('is-pusat-staff', fn(User $user) => $user->hasRole(['KG', 'AG']));
        // Staf operasional di Dealer
        Gate::define('is-dealer-staff', fn(User $user) => $user->hasRole(['KC', 'AD', 'CS', 'KSR', 'SLS']));


        // =================================================================
        // DEFINISI GATE BERDASARKAN TUGAS (PERMISSION-BASED)
        // =================================================================

        // --- PENGATURAN & MASTER DATA ---
        Gate::define('manage-users', fn(User $user) => $user->hasRole('SA'));
        Gate::define('manage-locations', fn(User $user) => $user->hasRole(['SA', 'PIC']));
        Gate::define('view-master-data', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA']));

        // --- PEMBELIAN (HANYA DI PUSAT) ---
        // ++ PERUBAHAN: Tambahkan 'is-pic' dan 'is-super-admin' untuk melihat menu ++
        Gate::define('access-po-module', fn(User $user) => $user->hasRole(['KG', 'AG', 'PIC', 'SA']));
        Gate::define('create-po', fn(User $user) => $user->hasRole('AG'));
        Gate::define('approve-po', function (User $user, $purchaseOrder) { // Gate ini tetap (untuk aksi)
            return $user->hasRole('KG') && $user->lokasi && $user->lokasi->tipe === 'PUSAT' && $user->gudang_id === $purchaseOrder->gudang_id;
        });
        // ++ PERUBAHAN: Tambahkan 'is-pic' dan 'is-super-admin' untuk melihat menu ++
        Gate::define('manage-purchase-returns', function(User $user) {
            return $user->hasRole(['AG', 'PIC', 'SA']) && $user->lokasi && $user->lokasi->tipe === 'PUSAT';
        });

        // --- OPERASIONAL GUDANG & DEALER ---
        // ++ PERUBAHAN: Tambahkan 'is-pic' dan 'is-super-admin' untuk melihat menu ++
        Gate::define('perform-warehouse-ops', fn(User $user) => $user->hasRole(['AG', 'AD', 'PIC', 'SA']));
        Gate::define('create-stock-adjustment', fn(User $user) => $user->hasRole(['AG', 'AD']));
        Gate::define('manage-quarantine-stock', fn(User $user) => $user->hasRole(['AG', 'AD']));
        Gate::define('create-stock-transaction', fn(User $user) => $user->hasRole(['AG', 'AD']));

        Gate::define('approve-stock-transaction', function (User $user, $transaction) { // Gate ini tetap (untuk aksi)
            $lokasiId = $transaction->gudang_id ?? $transaction->gudang_asal_id;
            if (!$user->gudang_id) return false;

            if ($user->hasRole('KG') || $user->hasRole('KC')) {
                return $user->gudang_id === $lokasiId;
            }
            return false;
        });

        Gate::define('approve-stock-adjustment', function (User $user, $stockAdjustment) { // Gate ini tetap (untuk aksi)
            if (!$user->gudang_id || !$stockAdjustment->gudang_id) {
                return false;
            }
            if ($user->hasRole(['KG', 'KC'])) {
                return $user->gudang_id === $stockAdjustment->gudang_id;
            }
            return false;
        });

        // ++ PERUBAHAN: Tambahkan 'is-pic' dan 'is-super-admin' untuk melihat menu ++
        Gate::define('view-mutation-receiving', fn(User $user) => $user->hasRole(['AG', 'AD', 'KG', 'KC', 'PIC', 'SA']));
        Gate::define('receive-mutation', fn(User $user) => $user->hasRole(['AG', 'AD']));
        Gate::define('view-quarantine-stock', fn(User $user) => $user->hasRole(['AG', 'AD', 'KG', 'KC', 'PIC', 'SA']));

        // --- PENJUALAN ---
        Gate::define('access-sales-module', fn(User $user) => $user->hasRole(['SLS', 'KSR', 'CS']));
        Gate::define('view-sales', function(User $user) {
            return $user->hasRole(['SA', 'PIC', 'MA', 'KC', 'SLS', 'CS', 'KSR']);
        });
        Gate::define('create-sale', function(User $user) {
            return $user->hasRole(['SLS', 'CS']);
        });
        Gate::define('print-invoice-only', function(User $user) {
            return $user->hasRole('KSR');
        });

        // --- LAPORAN & MARKETING ---
        Gate::define('view-reports', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA', 'KG', 'KC']));
        Gate::define('manage-marketing', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA']));

        // --- AKSES KHUSUS KEPALA CABANG (READ-ONLY) ---
        Gate::define('is-read-only', fn(User $user) => $user->hasRole('KC'));

        // --- SERVICE ---
        Gate::define('view-service', function(User $user) {
            return $user->hasRole(['SA', 'PIC', 'MA', 'KC', 'CS', 'KSR']);
        });
        Gate::define('manage-service', function(User $user) {
            return $user->hasRole('CS');
        });
        
        Gate::define('export-service-report', function(User $user) {
            // Izinkan SA, PIC, Manager, Kpl Cabang, dan Counter Sales
            return $user->hasRole(['SA', 'PIC', 'MA', 'KC', 'CS']);
        });

        // ++ BARU: Gate Sederhana untuk Menu (tidak butuh model) ++
        Gate::define('view-stock-adjustments-menu', fn(User $user) => $user->hasRole(['SA', 'PIC', 'KG', 'KC', 'AG', 'AD']));
        Gate::define('view-stock-mutations-menu', fn(User $user) => $user->hasRole(['SA', 'PIC', 'KG', 'KC', 'AG', 'AD']));
    }
}
