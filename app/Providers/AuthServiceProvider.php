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

        // --- DEFINISI PERAN ---
        Gate::define('is-super-admin', fn(User $user) => $user->hasRole('SA'));
        Gate::define('is-pic', fn(User $user) => $user->hasRole('PIC'));
        Gate::define('is-manager', fn(User $user) => $user->hasRole('MA'));
        Gate::define('is-pusat-staff', fn(User $user) => $user->hasRole(['KG', 'AG']));
        Gate::define('is-dealer-staff', fn(User $user) => $user->hasRole(['KC', 'PC', 'KSR'])); 
        Gate::define('is-asd', fn(User $user) => $user->hasRole('ASD'));

        // =================================================================
        // 1. MODUL INBOUND (PENERIMAAN)
        // =================================================================
        
        // Gate Umum (Induk Menu): PC HARUS ADA agar menu induk "Penerimaan" muncul
        Gate::define('perform-warehouse-ops', function (User $user) {
            if ($user->hasRole(['SA', 'PIC'])) return true;
            return $user->hasRole(['AG', 'KG', 'PC']); 
        });

        // Gate Menu Sidebar (KG Hidden)
        Gate::define('perform-warehouse-ops-exclude-kg', function (User $user) {
            if ($user->hasRole(['SA', 'PIC'])) return true;
            return $user->hasRole(['AG', 'PC']); 
        });

        // ++ GATE BARU: KHUSUS MENU QC (PC DIHAPUS DARI SINI) ++
        // Hanya Pusat (AG) yang butuh menu QC. Dealer (PC) bypass QC.
        Gate::define('view-qc-menu', function (User $user) {
            return $user->hasRole(['SA', 'PIC', 'AG', 'KG']); 
        });

        // ++ REVISI: STOK KARANTINA (PC DIHAPUS DARI SINI) ++
        // Dealer tidak ada retur/karantina
        Gate::define('manage-quarantine-stock', function(User $user) {
             return $user->hasRole(['SA', 'PIC', 'AG', 'KG']);
        });

        Gate::define('view-mutation-receiving', fn(User $user) => $user->hasRole(['KG', 'KC', 'PIC', 'SA', 'PC', 'AG']));
        Gate::define('receive-mutation', fn(User $user) => $user->hasRole(['AG', 'PC']));


        // =================================================================
        // 2. MODUL TRANSAKSI STOK (MUTASI & ADJUSTMENT)
        // =================================================================
        

        
        Gate::define('create-stock-transaction', fn(User $user) => $user->hasRole(['SA', 'PIC', 'PC']));
        Gate::define('approve-stock-transaction', fn(User $user) => $user->hasRole(['SA', 'PIC', 'ASD']));

        // --- Mutasi Stok ---
        Gate::define('view-stock-mutations-menu', function(User $user) {
            return $user->hasRole(['SA', 'PIC', 'PC']);
        });
        Gate::define('create-stock-mutation', function (User $user) {
            return $user->hasRole(['SA', 'PIC', 'PC']);
        });
        // --- Adjustment ---
        Gate::define('view-stock-adjustments-menu', function (User $user) {
             return $user->hasRole(['SA', 'PIC', 'ACC', 'AG', 'KG', 'KC', 'SMD']);
        });
        Gate::define('create-stock-adjustment', function (User $user) {
            return $user->hasRole(['SA', 'PIC', 'AG', 'KC', 'SMD']);
        });
        Gate::define('approve-stock-adjustment', fn(User $user) => $user->hasRole(['SA', 'PIC', 'KG', 'KC', 'ASD']));


        // =================================================================
        // 3. MODUL PEMBELIAN (PO)
        // =================================================================
        Gate::define('create-po', function (User $user) {
            return $user->hasRole(['SA', 'PIC', 'AG', 'SMD']); 
        });
        Gate::define('approve-po', function (User $user, $po = null) {
            if ($user->hasRole(['SA', 'PIC'])) return true;
            if ($po) {
                if ($po->po_type == 'dealer_request') return $user->hasRole('AG');
                if ($po->po_type == 'supplier_po') return $user->hasRole(['KG', 'MA']);
            } else {
                return $user->hasRole(['AG', 'KG', 'MA']);
            }
            return false;
        });
        Gate::define('view-po-module', function ($user) {
             return $user->can('create-po') || $user->can('approve-po') || $user->hasRole(['SA', 'PIC', 'MA', 'SMD', 'ACC', 'AG', 'KG']);
        });
        Gate::define('manage-purchase-returns', function(User $user) {
            return $user->hasRole(['PIC', 'SA', 'AG']) && $user->lokasi && $user->lokasi->tipe === 'PUSAT';
        });

        // =================================================================
        // 4. LAINNYA
        // =================================================================
        Gate::define('manage-users', fn(User $user) => $user->hasRole('SA'));
        Gate::define('manage-locations-sa-only', fn(User $user) => $user->hasRole('SA'));
        Gate::define('manage-locations', fn(User $user) => $user->hasRole(['SA', 'PIC', 'AG']));
        Gate::define('view-master-data', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA']));
        Gate::define('manage-barangs', fn(User $user) => $user->hasRole(['SA', 'PIC', 'ASD', 'SMD']));
        Gate::define('manage-converts', fn(User $user) => $user->hasRole(['SA', 'PIC', 'ASD']));
        Gate::define('access-sales-module', fn(User $user) => $user->hasRole(['SLS', 'KSR', 'PC']));
        Gate::define('view-sales', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA', 'KC', 'SLS', 'PC', 'KSR', 'ASD', 'ACC']));
        Gate::define('create-sale', fn(User $user) => $user->hasRole(['SLS', 'PC']));
        Gate::define('print-invoice-only', fn(User $user) => $user->hasRole('KSR'));
        Gate::define('view-service', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA', 'KC', 'PC', 'KSR', 'ASD', 'ACC']));
        Gate::define('manage-service', fn(User $user) => $user->hasRole(['PC', 'KSR']));
        Gate::define('export-service-report', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA', 'KC', 'PC', 'KSR', 'ASD', 'ACC']));
        Gate::define('manage-marketing', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA']));
        Gate::define('view-reports', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA', 'KG', 'KC', 'SMD', 'ACC', 'AG', 'PC', 'ASD']));
        Gate::define('view-stock-card', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA', 'AG', 'KG', 'KC', 'PC'])); 
        Gate::define('view-stock-by-warehouse', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA', 'KG', 'KC', 'AG', 'PC', 'ASD']));
        Gate::define('view-stock-report-global', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA', 'ACC', 'AG', 'SMD', 'ASD']));
        Gate::define('view-sales-summary', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA', 'KC', 'ACC', 'SLS', 'PC', 'ASD'])); 
        Gate::define('view-service-summary', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA', 'KC', 'ACC', 'PC', 'ASD'])); 
        Gate::define('view-purchase-journal', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA', 'ACC'])); 
        Gate::define('view-inventory-value', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA', 'ACC'])); 
    }
}