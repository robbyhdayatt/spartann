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
        Gate::define('is-dealer-staff', fn(User $user) => $user->hasRole(['KC', 'AD', 'CS', 'KSR', 'SLS']));

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
            return $user->hasRole(['AG', 'PIC', 'SA']) && $user->lokasi && $user->lokasi->tipe === 'PUSAT';
        });

        // 3. MODUL INBOUND
        Gate::define('perform-warehouse-ops', function (User $user) {
            if ($user->hasRole(['SA', 'PIC'])) return true;
            if (!$user->lokasi) return false;
            if ($user->lokasi->tipe === 'PUSAT') return false;
            if ($user->hasRole(['AG', 'AD', 'KG', 'KC'])) return true;
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
        
        Gate::define('view-mutation-receiving', fn(User $user) => $user->hasRole(['AG', 'AD', 'KG', 'KC', 'PIC', 'SA']));
        Gate::define('receive-mutation', fn(User $user) => $user->hasRole(['AG', 'AD']));
        Gate::define('manage-quarantine-stock', fn(User $user) => $user->can('perform-warehouse-ops'));
        Gate::define('view-quarantine-stock', fn(User $user) => $user->can('perform-warehouse-ops'));

        // 5. MASTER DATA & SETTINGS
        Gate::define('manage-users', fn(User $user) => $user->hasRole('SA'));
        Gate::define('manage-locations', fn(User $user) => $user->hasRole(['SA', 'PIC']));
        Gate::define('view-master-data', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA', 'SMD']));
        Gate::define('manage-barangs', fn(User $user) => $user->hasRole(['SA', 'PIC', 'ASD', 'SMD']));
        Gate::define('manage-converts', fn(User $user) => $user->hasRole(['SA', 'PIC', 'ASD']));

        // =================================================================
        // 6. PENJUALAN & SERVICE
        // =================================================================
        Gate::define('access-sales-module', fn(User $user) => $user->hasRole(['SLS', 'KSR', 'CS']));
        
        // KSR sudah ada di view-sales
        Gate::define('view-sales', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA', 'KC', 'SLS', 'CS', 'KSR', 'ASD']));
        
        // ++ PERUBAHAN: Tambahkan KSR agar bisa buat penjualan ++
        Gate::define('create-sale', fn(User $user) => $user->hasRole(['SLS', 'CS', 'KSR']));
        
        // Gate ini mungkin tidak lagi relevan jika KSR bisa create sale, tapi dibiarkan saja tidak apa-apa
        Gate::define('print-invoice-only', fn(User $user) => $user->hasRole('KSR'));

        // KSR sudah ada di view-service
        Gate::define('view-service', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA', 'KC', 'CS', 'KSR', 'ASD', 'ACC']));
        
        // ++ PERUBAHAN: Tambahkan KSR agar bisa kelola service (edit/tambah part) ++
        Gate::define('manage-service', fn(User $user) => $user->hasRole(['CS', 'KSR',]));
        
        // ++ PERUBAHAN: Tambahkan KSR agar bisa export laporan service ++
        Gate::define('export-service-report', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA', 'KC', 'CS', 'KSR', 'ASD', 'ACC']));

        // 7. LAPORAN
        Gate::define('view-reports', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA', 'KG', 'KC', 'SMD', 'ACC']));
        Gate::define('view-global-reports', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA', 'SMD', 'ACC']));
        Gate::define('view-purchase-journal', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA', 'KG', 'SMD', 'ACC']));
        Gate::define('manage-marketing', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA']));
    }
}