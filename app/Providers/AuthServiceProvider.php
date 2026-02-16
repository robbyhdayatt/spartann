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

        // --- DEFINISI PERAN UTAMA ---
        Gate::define('is-super-admin', fn(User $user) => $user->hasRole('SA'));
        Gate::define('is-pic', fn(User $user) => $user->hasRole('PIC'));
        // is-manager dihapus karena MA dihapus
        Gate::define('is-pusat-staff', fn(User $user) => $user->hasRole(['KG', 'AG']));
        Gate::define('is-dealer-staff', fn(User $user) => $user->hasRole(['KC', 'PC', 'KSR'])); 
        Gate::define('is-asd', fn(User $user) => $user->hasRole('ASD'));

        // =================================================================
        // 1. MODUL INBOUND (PENERIMAAN)
        // =================================================================
        
        // Gate Umum: PC butuh akses penerimaan
        Gate::define('perform-warehouse-ops', function (User $user) {
            if ($user->hasRole(['SA', 'PIC'])) return true;
            return $user->hasRole(['AG', 'KG', 'PC']); 
        });

        // Gate Menu Sidebar
        Gate::define('perform-warehouse-ops-exclude-kg', function (User $user) {
            if ($user->hasRole(['SA', 'PIC'])) return true;
            return $user->hasRole(['AG', 'PC']); 
        });

        // QC: Hanya Pusat
        Gate::define('view-qc-menu', function (User $user) {
            return $user->hasRole(['SA', 'PIC', 'AG', 'KG']); 
        });

        // Karantina: Hanya Pusat
        Gate::define('view-quarantine-stock', function(User $user) {
             return $user->hasRole(['SA', 'PIC', 'AG', 'KG']);
        });
        
        Gate::define('manage-quarantine-stock', function(User $user) {
             return $user->hasRole(['SA', 'PIC', 'AG', 'KG']);
        });

        // Mutasi Penerimaan
        Gate::define('view-mutation-receiving', fn(User $user) => $user->hasRole(['KG', 'KC', 'PIC', 'SA', 'PC', 'AG']));
        Gate::define('receive-mutation', fn(User $user) => $user->hasRole(['AG', 'PC']));


        // =================================================================
        // 2. MODUL TRANSAKSI STOK (MUTASI & ADJUSTMENT)
        // =================================================================
        
        Gate::define('create-stock-transaction', fn(User $user) => $user->hasRole(['SA', 'PIC', 'PC']));
        // Approve Stock Transaction: Pusat & ASD
        Gate::define('approve-stock-transaction', fn(User $user) => $user->hasRole(['SA', 'PIC', 'ASD', 'IMS']));

        // --- Mutasi Stok ---
        Gate::define('view-stock-mutations-menu', function(User $user) {
            return $user->hasRole(['SA', 'PIC', 'PC', 'ASD', 'IMS']);
        });
        Gate::define('create-stock-mutation', function (User $user) {
            return $user->hasRole(['SA', 'PIC', 'PC']);
        });
        
        // --- Adjustment ---
        Gate::define('view-stock-adjustments-menu', function (User $user) {
             return $user->hasRole(['SA', 'PIC', 'ACC', 'AG', 'KG', 'KC', 'IMS', 'ASD']);
        });
        Gate::define('create-stock-adjustment', function (User $user) {
            return $user->hasRole(['SA', 'PIC', 'AG', 'KC', 'IMS']); // IMS (ex SMD) bisa create
        });
        Gate::define('approve-stock-adjustment', fn(User $user) => $user->hasRole(['SA', 'PIC', 'KG', 'KC', 'ASD']));


        // =================================================================
        // 3. MODUL PEMBELIAN (PO)
        // =================================================================
        Gate::define('create-po', function (User $user) {
            // IMS (Inventory MD) boleh create PO
            return $user->hasRole(['SA', 'PIC', 'AG', 'IMS']); 
        });
        Gate::define('approve-po', function (User $user, $po = null) {
            if ($user->hasRole(['SA', 'PIC'])) return true;
            if ($po) {
                if ($po->po_type == 'dealer_request') return $user->hasRole('AG'); // Admin Gudang approve request dealer
                if ($po->po_type == 'supplier_po') return $user->hasRole(['KG', 'ASD']); // KG/ASD approve PO Supplier
            } else {
                return $user->hasRole(['AG', 'KG', 'ASD']);
            }
            return false;
        });
        Gate::define('view-po-module', function ($user) {
             return $user->can('create-po') || $user->can('approve-po') || $user->hasRole(['SA', 'PIC', 'IMS', 'ACC', 'AG', 'KG', 'ASD']);
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
        Gate::define('view-master-data', fn(User $user) => $user->hasRole(['SA', 'PIC', 'ASD']));
        Gate::define('manage-barangs', fn(User $user) => $user->hasRole(['SA', 'PIC', 'ASD', 'IMS', 'AG']));
        Gate::define('manage-converts', fn(User $user) => $user->hasRole(['SA', 'PIC', 'ASD']));
        
        // Sales Module: Hanya PC dan KSR (karena SLS dihapus)
        Gate::define('access-sales-module', fn(User $user) => $user->hasRole(['KSR', 'PC']));
        Gate::define('view-sales', fn(User $user) => $user->hasRole(['SA', 'PIC', 'KC', 'PC', 'KSR', 'ASD', 'ACC', 'IMS']));
        Gate::define('create-sale', fn(User $user) => $user->hasRole(['PC'])); // Sales dihapus, PC bisa jual
        Gate::define('print-invoice-only', fn(User $user) => $user->hasRole('KSR'));
        
        Gate::define('view-service', fn(User $user) => $user->hasRole(['SA', 'PIC', 'KC', 'PC', 'KSR', 'ASD', 'ACC', 'IMS']));
        Gate::define('manage-service', fn(User $user) => $user->hasRole(['PC', 'KSR']));
        Gate::define('export-service-report', fn(User $user) => $user->hasRole(['SA', 'PIC', 'KC', 'PC', 'KSR', 'ASD', 'ACC']));
        
        Gate::define('view-reports', fn(User $user) => $user->hasRole(['SA', 'PIC', 'KG', 'KC', 'IMS', 'ACC', 'AG', 'PC', 'ASD']));
        Gate::define('view-stock-card', fn(User $user) => $user->hasRole(['SA', 'PIC', 'AG', 'KG', 'KC', 'PC', 'ASD', 'IMS'])); 
        Gate::define('view-stock-by-warehouse', fn(User $user) => $user->hasRole(['SA', 'PIC', 'KG', 'KC', 'AG', 'PC', 'ASD', 'IMS']));
        Gate::define('view-stock-report-global', fn(User $user) => $user->hasRole(['SA', 'PIC', 'ACC', 'AG', 'IMS', 'ASD']));
        Gate::define('view-sales-summary', fn(User $user) => $user->hasRole(['SA', 'PIC', 'KC', 'ACC', 'PC', 'ASD'])); 
        Gate::define('view-service-summary', fn(User $user) => $user->hasRole(['SA', 'PIC', 'KC', 'ACC', 'PC', 'ASD'])); 
        Gate::define('view-purchase-journal', fn(User $user) => $user->hasRole(['SA', 'PIC', 'ACC', 'IMS'])); 
        Gate::define('view-inventory-value', fn(User $user) => $user->hasRole(['SA', 'PIC', 'ACC', 'IMS'])); 
    }
}