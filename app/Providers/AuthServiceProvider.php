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

        // 1. GLOBAL ACCESS (SA)
        // Poin 1: SA bisa mengakses seluruh modul (Bypass All)
        Gate::before(fn(User $user) => $user->hasRole('SA') ? true : null);

        // --- HELPER GATES (Untuk Kodingan Blade/Controller) ---
        // Poin 2: PIC View Only All
        Gate::define('is-pic', fn(User $user) => $user->hasRole('PIC'));
        
        // Helper Grup Role
        Gate::define('is-global', fn(User $user) => $user->hasRole(['SA', 'PIC'])); // SA & PIC
        Gate::define('is-pusat', fn(User $user) => $user->lokasi && $user->lokasi->tipe === 'PUSAT');
        Gate::define('is-gudang', fn(User $user) => $user->lokasi && $user->lokasi->tipe === 'GUDANG');
        Gate::define('is-dealer', fn(User $user) => $user->lokasi && $user->lokasi->tipe === 'DEALER');


        // =================================================================
        // GROUP A: MASTER DATA (Poin 3 - 8)
        // =================================================================

        // Poin 3: Menu Pengguna (Hanya SA)
        Gate::define('manage-users', fn(User $user) => false); // SA handled by before()

        // MANAJEMEN JABATAN (Hanya SA)
        Gate::define('manage-jabatans', fn(User $user) => $user->hasRole('SA'));

        // Poin 4: Menu Lokasi (SA & PIC View Only)
        Gate::define('view-lokasi', fn(User $user) => $user->isGlobal());
        Gate::define('manage-lokasi', fn(User $user) => false); // Hanya SA

        // Poin 5: Menu Rak
        Gate::define('view-raks', function (User $user) {
            if ($user->isGlobal()) return true;
            if ($user->hasRole(['AG', 'KG']) && $user->isGudang()) return true; // Gudang
            if ($user->hasRole(['ASD', 'IMS', 'ACC']) && $user->isPusat()) return true; // Pusat
            if ($user->hasRole(['KC', 'PC']) && $user->isDealer()) return true; // Dealer
            return false;
        });
        Gate::define('manage-raks', fn(User $user) => false); // Hanya SA (Full Akses)

        // Poin 6: Menu Supplier
        Gate::define('view-supplier', fn(User $user) => $user->isGlobal() || $user->hasRole(['AG', 'KG']));
        Gate::define('manage-supplier', fn(User $user) => $user->hasRole(['AG', 'KG']));

        // Poin 7: Master Convert
        Gate::define('view-convert', fn(User $user) => $user->isGlobal() || $user->hasRole('ASD'));
        Gate::define('manage-convert', fn(User $user) => $user->hasRole('ASD'));

        // Poin 8: Master Item (Barang)
        // Akses Menu
        Gate::define('view-barang', function (User $user) {
            return $user->isGlobal() || 
                   $user->hasRole(['ASD', 'IMS', 'ACC']) || 
                   $user->hasRole(['AG', 'KG']);
        });
        Gate::define('manage-barang', function (User $user) {
            return $user->hasRole(['ASD', 'IMS', 'ACC', 'AG', 'KG']);
        });

        // Poin 8 Detail: Visibility Harga
        // ASD, IMS, ACC (Full kecuali Selling In) -> Bisa lihat Selling Out & Retail
        // AG, KG (Full kecuali Selling Out & Retail) -> Bisa lihat Selling In
        Gate::define('view-price-selling-in', function (User $user) {
            // SA/PIC + AG/KG
            return $user->isGlobal() || $user->hasRole(['AG', 'KG']);
        });
        Gate::define('view-price-selling-out', function (User $user) {
            // SA/PIC + ASD/IMS/ACC
            return $user->isGlobal() || $user->hasRole(['ASD', 'IMS', 'ACC']);
        });


        // =================================================================
        // GROUP B: PEMBELIAN & INBOUND (Poin 9 - 14)
        // =================================================================

        // [FIX UTAMA] Definisi Gate Umum 'create-po' untuk Menu & Tombol
        Gate::define('create-po', function (User $user) {
            // SA/PIC (Global)
            if ($user->isGlobal()) return true;
            
            // AG (Gudang) -> Bikin PO Supplier
            if ($user->hasRole('AG') && $user->isGudang()) return true;
            
            // IMS (Pusat) -> Bikin Dealer Request
            if ($user->hasRole('IMS') && $user->isPusat()) return true;
            
            // PC (Dealer) -> Bikin Dealer Request (untuk diri sendiri)
            if ($user->hasRole('PC') && $user->isDealer()) return true;

            return false;
        });

        // Poin 9: Menu PO (View List)
        Gate::define('view-po', function (User $user) {
            // Ditambahkan PC & KC agar Dealer bisa lihat list PO request mereka sendiri
            return $user->isGlobal() || 
                   $user->hasRole(['AG', 'IMS', 'KG', 'ASD', 'ACC']) ||
                   ($user->isDealer() && $user->hasRole(['PC', 'KC']));
        });
        // Create PO
        Gate::define('create-po-supplier', fn(User $user) => $user->hasRole('AG'));
        Gate::define('create-po-dealer', fn(User $user) => $user->hasRole('IMS'));
        // Approve PO
        Gate::define('approve-po-dealer', fn(User $user) => $user->hasRole('AG'));
        Gate::define('approve-po-supplier', fn(User $user) => $user->hasRole('KG'));

        // Poin 10: Retur Pembelian
        Gate::define('view-retur-pembelian', fn(User $user) => $user->isGlobal() || $user->hasRole(['AG', 'KG']));
        Gate::define('create-retur-pembelian', fn(User $user) => $user->hasRole('AG'));

        // Poin 11: Receiving
        Gate::define('view-receiving', fn(User $user) => $user->isGlobal() || $user->hasRole(['AG', 'PC']));
        Gate::define('process-receiving-gudang', fn(User $user) => $user->hasRole('AG'));
        Gate::define('process-receiving-dealer', fn(User $user) => $user->hasRole('PC'));

        // Poin 12: Quality Control (QC)
        Gate::define('view-qc', fn(User $user) => $user->isGlobal() || $user->hasRole('AG'));
        Gate::define('process-qc', fn(User $user) => $user->hasRole('AG'));

        // Poin 13: Putaway
        Gate::define('view-putaway', fn(User $user) => $user->isGlobal() || $user->hasRole(['AG', 'PC']));
        Gate::define('process-putaway-gudang', fn(User $user) => $user->hasRole('AG'));
        Gate::define('process-putaway-dealer', fn(User $user) => $user->hasRole('PC'));

        // Poin 14: Stok Karantina
        Gate::define('view-karantina', fn(User $user) => $user->isGlobal() || $user->hasRole(['AG', 'KG']));
        Gate::define('manage-karantina', fn(User $user) => $user->hasRole('AG')); // AG Full Akses


        // =================================================================
        // GROUP C: TRANSAKSI STOK & PENJUALAN (Poin 15 - 17)
        // =================================================================

        // Poin 15: Adjustment Stok
        Gate::define('view-adjustment', function (User $user) {
            return $user->isGlobal() || $user->hasRole(['AG', 'KG', 'ACC', 'IMS']);
        });
        Gate::define('create-adjustment-gudang', fn(User $user) => $user->hasRole('AG'));
        Gate::define('approve-adjustment-gudang', fn(User $user) => $user->hasRole('KG'));
        // ACC, IMS create only seluruh dealer (Approve dealer logic belum didefinisikan, sementara open/auto)
        Gate::define('create-adjustment-dealer', fn(User $user) => $user->hasRole(['ACC', 'IMS']));

        // Poin 16: Service
        Gate::define('view-service', function (User $user) {
            return $user->isGlobal() || $user->hasRole(['PC', 'KC', 'KSR', 'ASD', 'ACC']);
        });
        Gate::define('manage-service', fn(User $user) => $user->hasRole('PC')); // PC Manage Dealer Masing2

        // Poin 17: Penjualan
        Gate::define('view-penjualan', function (User $user) {
            return $user->isGlobal() || $user->hasRole(['PC', 'KC', 'KSR', 'ASD', 'ACC']);
        });
        Gate::define('manage-penjualan', fn(User $user) => $user->hasRole('PC')); // PC Manage Dealer Masing2


        // =================================================================
        // GROUP D: REPORTING & LOGIKA HARGA (Poin 18 - 24)
        // =================================================================

        // Poin 18: Kartu Stok & Poin 19: Laporan Stok Per Lokasi
        // User Global: All
        // User Gudang (AG, KG): Only Gudang
        // User Pusat (ACC, IMS, ASD): Only Dealer
        // User Dealer (KC, PC): Only Dealer Masing2
        Gate::define('view-stock-card', function (User $user) {
            return $user->isGlobal() || $user->isGudang() || $user->isPusat() || $user->isDealer();
        });
        Gate::define('view-stock-location-report', function (User $user) {
            return $user->isGlobal() || $user->isGudang() || $user->isPusat() || $user->isDealer();
        });

        // Poin 20: Laporan Stok Total
        Gate::define('view-stock-total-report', fn(User $user) => $user->isGlobal()); // SA & PIC

        // Poin 21: Laporan Penjualan
        Gate::define('view-sales-report', function (User $user) {
            return $user->isGlobal() || 
                   ($user->isPusat() && $user->hasRole(['ASD', 'ACC'])) || 
                   ($user->isDealer() && $user->hasRole(['PC', 'KC']));
        });

        // Poin 22: Laporan Service
        Gate::define('view-service-report', function (User $user) {
            return $user->isGlobal() || 
                   ($user->isPusat() && $user->hasRole(['ASD', 'ACC'])) || 
                   ($user->isDealer() && $user->hasRole(['PC', 'KC']));
        });

        // Poin 23: Jurnal Pembelian
        Gate::define('view-purchase-journal', function (User $user) {
            return $user->isGlobal() || 
                   ($user->isPusat() && $user->hasRole(['IMS', 'ACC'])) || // Only PO Dealer
                   ($user->isGudang() && $user->hasRole(['AG', 'KG']));    // Only PO Supplier
        });

        // Poin 24: Laporan Nilai Persediaan
        Gate::define('view-inventory-value-report', function (User $user) {
            return $user->isGlobal() || $user->isGudang() || $user->isPusat() || $user->isDealer();
        });

        // --- GATE KHUSUS VISIBILITAS HARGA DI LAPORAN (Poin 19 & 24) ---
        // Gudang: Only Selling In
        // Pusat & Dealer: Only Selling Out & Retail
        Gate::define('report-show-selling-in', function (User $user) {
            return $user->isGlobal() || $user->isGudang();
        });
        
        Gate::define('report-show-selling-out-retail', function (User $user) {
            return $user->isGlobal() || $user->isPusat() || $user->isDealer();
        });
    }
}