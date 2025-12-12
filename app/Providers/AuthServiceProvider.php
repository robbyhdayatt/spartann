<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [];

    public function boot()
    {
        $this->registerPolicies();

        Gate::before(fn(User $user) => $user->hasRole('SA') ? true : null);

        // 1. DEFINISI PERAN
        Gate::define('is-super-admin', fn(User $user) => $user->hasRole('SA'));
        Gate::define('is-pic', fn(User $user) => $user->hasRole('PIC'));
        Gate::define('is-manager', fn(User $user) => $user->hasRole('MA'));
        Gate::define('is-service-md', fn(User $user) => $user->hasRole('SMD'));
        Gate::define('is-accounting', fn(User $user) => $user->hasRole('ACC'));
        
        Gate::define('is-pusat-staff', fn(User $user) => $user->hasRole(['KG', 'AG']));
        // PERUBAHAN: Ganti 'CS' jadi 'PC'
        Gate::define('is-dealer-staff', fn(User $user) => $user->hasRole(['KC', 'AD', 'PC', 'KSR', 'SLS']));

        // 2. MODUL PEMBELIAN (PO)
        Gate::define('create-po', function (User $user) {
            if ($user->hasRole(['SA', 'PIC', 'SMD'])) return true;
            if (!$user->lokasi) return false;
            if ($user->lokasi->tipe === 'PUSAT') return false;
            return false; 
        });

        Gate::define('approve-po', function (User $user, $po = null) {
            if ($user->hasRole(['SA', 'PIC'])) return true;
            if ($user->hasRole('AG') && $user->lokasi && $user->lokasi->tipe === 'PUSAT') return true;
            return false;
        });

        Gate::define('view-po-module', function ($user) {
             return $user->can('create-po') || $user->can('approve-po') || $user->hasRole(['SA', 'PIC', 'MA', 'SMD', 'ACC', 'AG']);
        });

        Gate::define('manage-purchase-returns', function(User $user) {
            return $user->hasRole([ 'PIC', 'SA']) && $user->lokasi && $user->lokasi->tipe === 'PUSAT';
        });

        // 3. MODUL INBOUND (Penerimaan, QC, Putaway)
        Gate::define('perform-warehouse-ops', function (User $user) {
            if ($user->hasRole(['SA', 'PIC'])) return true;
            if (!$user->lokasi) return false;
            if ($user->lokasi->tipe === 'PUSAT') return false;
            // PERUBAHAN: Ganti 'CS' jadi 'PC'
            if ($user->hasRole(['AG', 'AD', 'KG', 'KC', 'PC'])) return true;
            return false;
        });

        // 4. MODUL TRANSAKSI STOK
        Gate::define('create-stock-transaction', function (User $user) {
            if ($user->hasRole(['SA', 'PIC', 'ACC'])) return true;
            if (!$user->lokasi || $user->lokasi->tipe === 'PUSAT') return false;
            return $user->hasRole(['AG', 'KG', 'KC']); 
        });

        Gate::define('create-stock-adjustment', function (User $user) {
            if ($user->hasRole(['SA', 'PIC', 'ACC'])) return true;
            if (!$user->lokasi || $user->lokasi->tipe === 'PUSAT') return false;
            return $user->hasRole(['AG', 'KG', 'KC']);
        });

        Gate::define('approve-stock-transaction', fn(User $user) => $user->hasRole(['SA', 'PIC', 'KG', 'KC']));
        Gate::define('approve-stock-adjustment', fn(User $user) => $user->hasRole(['SA', 'PIC', 'KG', 'KC']));

        Gate::define('view-stock-mutations-menu', function ($user) {
             return $user->can('create-stock-transaction') || $user->can('approve-stock-transaction') || $user->hasRole(['SA', 'PIC', 'ACC']);
        });
        Gate::define('view-stock-adjustments-menu', function ($user) {
             return $user->can('create-stock-adjustment') || $user->can('approve-stock-adjustment') || $user->hasRole(['SA', 'PIC', 'ACC']);
        });
        
        // PERUBAHAN: Ganti 'CS' jadi 'PC'
        Gate::define('view-mutation-receiving', fn(User $user) => $user->hasRole(['AD', 'KG', 'KC', 'PIC', 'SA', 'PC']));
        Gate::define('receive-mutation', fn(User $user) => $user->hasRole(['AG', 'AD', 'PC']));
        
        Gate::define('manage-quarantine-stock', fn(User $user) => $user->can('perform-warehouse-ops'));
        Gate::define('view-quarantine-stock', fn(User $user) => $user->can('perform-warehouse-ops'));

        // 5. MASTER DATA & SETTINGS
        Gate::define('manage-users', fn(User $user) => $user->hasRole('SA'));
        Gate::define('manage-locations', fn(User $user) => $user->hasRole(['SA', 'PIC']));
        Gate::define('view-master-data', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA']));
        Gate::define('manage-barangs', fn(User $user) => $user->hasRole(['SA', 'PIC', 'ASD', 'SMD']));
        Gate::define('manage-converts', fn(User $user) => $user->hasRole(['SA', 'PIC', 'ASD']));

        // =================================================================
        // 6. PENJUALAN & SERVICE
        // =================================================================
        // PERUBAHAN: Ganti 'CS' jadi 'PC'
        Gate::define('access-sales-module', fn(User $user) => $user->hasRole(['SLS', 'KSR', 'PC']));
        
        // PERUBAHAN: Ganti 'CS' jadi 'PC'
        Gate::define('view-sales', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA', 'KC', 'SLS', 'PC', 'KSR', 'ASD']));
        
        // PERUBAHAN: Ganti 'CS' jadi 'PC'
        Gate::define('create-sale', fn(User $user) => $user->hasRole(['SLS', 'PC', 'KSR']));
        
        Gate::define('print-invoice-only', fn(User $user) => $user->hasRole('KSR'));

        // PERUBAHAN: Ganti 'CS' jadi 'PC'
        Gate::define('view-service', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA', 'KC', 'PC', 'KSR', 'ASD', 'ACC']));
        
        // PERUBAHAN: Ganti 'CS' jadi 'PC'
        Gate::define('manage-service', fn(User $user) => $user->hasRole(['PC', 'KSR',]));
        
        // PERUBAHAN: Ganti 'CS' jadi 'PC'
        Gate::define('export-service-report', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA', 'KC', 'PC', 'KSR', 'ASD', 'ACC']));

        // 7. LAPORAN
        Gate::define('view-reports', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA', 'KG', 'KC', 'SMD', 'ACC']));
        Gate::define('view-global-reports', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA', 'SMD', 'ACC']));
        Gate::define('view-purchase-journal', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA', 'KG', 'SMD', 'ACC']));
        Gate::define('manage-marketing', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA']));
    }
}