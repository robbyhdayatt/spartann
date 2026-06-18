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
        Gate::before(fn(User $user) => $user->hasRole('SA') ? true : null);

        // PIC View Only All
        Gate::define('is-pic', fn(User $user) => $user->hasRole('PIC'));
        
        // Helper Grup Role
        Gate::define('is-global', fn(User $user) => $user->hasRole(['SA', 'PIC']));
        Gate::define('is-pusat', fn(User $user) => $user->lokasi && $user->lokasi->tipe === 'PUSAT');
        Gate::define('is-gudang', fn(User $user) => $user->lokasi && $user->lokasi->tipe === 'GUDANG');
        Gate::define('is-dealer', fn(User $user) => $user->lokasi && $user->lokasi->tipe === 'DEALER');


        // =================================================================
        // GROUP A: MASTER DATA
        // =================================================================

        // Menu Pengguna (Hanya SA)
        Gate::define('manage-users', fn(User $user) => false);

        // MANAJEMEN JABATAN (Hanya SA)
        Gate::define('manage-jabatans', fn(User $user) => $user->hasRole('SA'));

        // Menu Lokasi (SA & PIC View Only)
        Gate::define('view-lokasi', fn(User $user) => $user->hasRole('SA'));
        Gate::define('manage-lokasi', fn(User $user) => $user->hasRole('SA'));

        // Menu Rak
        Gate::define('view-raks', function (User $user) {
            if ($user->hasRole('SA')) return true;
            if ($user->hasRole(['AG', 'KG']) && $user->isGudang()) return true; // Gudang
            if ($user->hasRole(['ASD', 'IMS', 'ACC']) && $user->isPusat()) return true; // Pusat
            if ($user->hasRole(['KC', 'PC']) && $user->isDealer()) return true; // Dealer
            return false;
        });
        Gate::define('manage-raks', fn(User $user) => $user->hasRole('SA')); // Hanya SA (Full Akses)

        // Menu Supplier
        Gate::define('view-supplier', fn(User $user) => $user->hasRole('SA') || $user->hasRole(['AG', 'KG']));
        Gate::define('manage-supplier', fn(User $user) => $user->hasRole('SA') || $user->hasRole(['AG', 'KG']));

        // Master Convert
        Gate::define('view-convert', fn(User $user) => $user->hasRole('SA') || $user->hasRole('ASD'));
        Gate::define('manage-convert', fn(User $user) => $user->hasRole('SA') || $user->hasRole('ASD'));

        // Master Item (Barang)
        Gate::define('view-barang', function (User $user) {
            return $user->hasRole('SA') || 
                   $user->hasRole(['ASD', 'IMS', 'ACC']) || 
                   $user->hasRole(['AG', 'KG']) ||
                   $user->hasRole(['KC', 'PC']);
        });
        Gate::define('manage-barang', function (User $user) {
            return $user->hasRole('SA') || $user->hasRole(['ASD', 'IMS', 'ACC', 'AG', 'KG']);
        });

        // [TAMBAHAN] Master Item YGP
        Gate::define('view-ygp', function (User $user) {
            return $user->hasRole('SA') || 
                   $user->hasRole(['ASD', 'IMS', 'ACC']) || 
                   $user->hasRole(['AG', 'KG']) ||
                   $user->hasRole(['KC', 'PC']);

        });
        Gate::define('manage-ygp', function (User $user) {
            return $user->hasRole('SA') || $user->hasRole(['ASD', 'IMS', 'ACC', 'AG', 'KG']);
        });

        // Detail: Visibility Harga
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
        // GROUP B: PEMBELIAN & INBOUND
        // =================================================================
        Gate::define('create-po', function (User $user) {
            // SA (Global)
            if ($user->hasRole('SA')) return true;
            
            // AG (Gudang) -> Bikin PO Supplier
            if ($user->hasRole('AG') && $user->isGudang()) return true;
            
            // IMS (Pusat) -> Bikin Dealer Request
            if ($user->hasRole('IMS') && $user->isPusat()) return true;

            return false;
        });

        // Menu PO (View List)
        Gate::define('view-po', function (User $user) {
            return $user->hasRole('SA') || 
                   $user->hasRole(['AG', 'IMS', 'KG', 'ASD', 'ACC']) 
                //    ||($user->isDealer() && $user->hasRole(['PC', 'KC']))
                   ;
        });

        // Create PO Khusus
        Gate::define('create-po-supplier', fn(User $user) => $user->hasRole('AG'));
        Gate::define('create-po-dealer', fn(User $user) => $user->hasRole('IMS'));

        // Approve PO
        Gate::define('approve-po', function (User $user, \App\Models\PurchaseOrder $po) {
            // Super Admin / PIC boleh approve semuanya
            if ($user->isGlobal()) return true;

            // Jika ini PO ke Supplier (Dibuat oleh AG) -> Yang Approve Kepala Gudang (KG)
            if ($po->po_type === 'supplier_po') {
                return $user->hasRole('KG') && $user->isGudang() && $user->lokasi_id == $po->lokasi_id;
            }

            // Jika ini Request dari Dealer (Dibuat oleh IMS/PC) -> Yang Approve Pusat / AG (Tergantung SOP Anda)
            // Sesuai dokumen: Gudang mendistribusikan ke dealer. Maka AG Gudang yang mengeksekusi/menyetujui pengiriman.
            if ($po->po_type === 'dealer_request') {
                return $user->hasRole('AG') && $user->isGudang() && $user->lokasi_id == $po->sumber_lokasi_id;
            }

            return false;
        });

        // Retur Pembelian
        Gate::define('view-retur-pembelian', fn(User $user) => $user->isGlobal() || $user->hasRole(['AG', 'KG']));
        Gate::define('create-retur-pembelian', fn(User $user) => $user->hasRole('AG'));

        // Receiving
        Gate::define('view-receiving', fn(User $user) => $user->hasRole(['SA', 'AG', 'PC']));
        Gate::define('process-receiving-gudang', fn(User $user) => $user->hasRole('AG'));
        Gate::define('process-receiving-dealer', fn(User $user) => $user->hasRole('PC'));

        // Quality Control (QC)
        Gate::define('view-qc', fn(User $user) => $user->hasRole('SA') || $user->hasRole('AG'));
        Gate::define('process-qc', fn(User $user) => $user->hasRole('AG'));

        // Putaway
        Gate::define('view-putaway', fn(User $user) => $user->hasRole(['SA', 'AG', 'PC']));
        Gate::define('process-putaway-gudang', fn(User $user) => $user->hasRole('AG'));
        Gate::define('process-putaway-dealer', fn(User $user) => $user->hasRole('PC'));

        // Stok Karantina
        Gate::define('view-karantina', fn(User $user) => $user->hasRole('SA') || $user->hasRole(['AG', 'KG']));
        Gate::define('manage-karantina', fn(User $user) => $user->hasRole('AG'));


        // =================================================================
        // GROUP C: TRANSAKSI STOK & PENJUALAN
        // =================================================================

        // Adjustment Stok
        
        // 1. View Adjustment
        Gate::define('view-stock-adjustment', function (User $user) {
            return $user->hasRole(['SA', 'ACC']);
        });

        // 2. Create Adjustment (Gabungan logika Gudang & Dealer)
        Gate::define('create-stock-adjustment', function (User $user) {
            // Global (SA/PIC) boleh
            if ($user->hasRole('SA')) return true;

            // Gudang: Admin Gudang (AG) boleh
            // if ($user->isGudang() && $user->hasRole('AG')) return true;

            // Dealer: Admin Pusat (ACC/IMS) boleh buat adjustment untuk dealer
            if ($user->isPusat() && $user->hasRole(['ACC', 'IMS'])) return true;

            // Dealer: Kepala Cabang (KC) atau Part Counter (PC) di Dealer (Opsional, sesuaikan kebutuhan)
            // if ($user->isDealer() && $user->hasRole(['KC', 'PC'])) return true;

            return false;
        });

        // 3. Approve Adjustment
        Gate::define('approve-stock-adjustment', function (User $user) {
            // Global (SA/PIC) boleh
            if ($user->hasRole('SA')) return true;

            // Gudang: Kepala Gudang (KG) approve kerjaan AG
            // if ($user->isGudang() && $user->hasRole('KG')) return true;
            
            return false;
        });

        // =================================================================
        // MUTASI STOK (UPDATED RULES)
        // =================================================================

        Gate::define('view-stock-transaction', function (User $user) {
            // 1 & 2: PIC, KC, dan seluruh role GUDANG tidak bisa view
            if ($user->hasRole(['PIC', 'KC']) || $user->isGudang()) {
                return false;
            }
            
            // Yang tersisa dan boleh View: SA, Pusat (ASD, IMS, ACC), dan PC (Dealer)
            return $user->hasRole(['SA', 'ASD', 'IMS', 'ACC', 'PC']);
        });

        Gate::define('create-stock-transaction', function (User $user) {
            // 1 & 2: PIC, KC, dan GUDANG tidak bisa create
            if ($user->hasRole(['PIC', 'KC']) || $user->isGudang()) {
                return false;
            }

            // 3: Role PUSAT (ASD, IMS, ACC) tidak bisa create
            if ($user->isPusat()) {
                return false;
            }

            // Yang tersisa dan boleh Create mutasi: SA (Super Admin) & PC (Dealer)
            return $user->hasRole(['SA', 'PC']);
        });

        Gate::define('approve-stock-transaction', function (User $user) {
            // 4: Yang melakukan approve HANYA role ACC 
            // (Saya tambahkan SA sebagai langkah aman agar Super Admin tidak terkunci dari sistemnya sendiri)
            return $user->hasRole(['SA', 'ACC']);
        });

        Gate::define('receive-stock-transaction', function (User $user, \App\Models\StockMutation $mutation) {
            // 1 & 2: PIC, KC, dan GUDANG tidak bisa receive
            if ($user->hasRole(['PIC', 'KC']) || $user->isGudang()) {
                return false;
            }

            // SA boleh menerima paksa dari mana saja (Bypass)
            if ($user->hasRole('SA')) {
                return true;
            }

            // Role lain (seperti PC di dealer atau ASD/IMS/ACC di pusat) 
            // hanya boleh menerima jika lokasi mereka sama dengan lokasi_tujuan_id
            return $user->lokasi_id == $mutation->lokasi_tujuan_id;
        });

        // Service
        Gate::define('view-service', function (User $user) {
            return $user->isGlobal() || $user->hasRole(['PC', 'KC', 'KSR', 'ASD', 'ACC']);
        });
        Gate::define('manage-service', fn(User $user) => $user->hasRole(['PC', 'KSR']));

        // Penjualan
        Gate::define('view-penjualan', function (User $user) {
            return $user->isGlobal() || $user->hasRole(['PC', 'KC', 'KSR', 'ASD', 'ACC']);
        });
        Gate::define('manage-penjualan', fn(User $user) => $user->hasRole('PC'));


        // =================================================================
        // GROUP D: REPORTING & LOGIKA HARGA
        // =================================================================

        // Kartu Stok & Laporan Stok Per Lokasi
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

        // Laporan Stok Total
        Gate::define('view-stock-total-report', fn(User $user) => $user->isGlobal()); // SA & PIC

        // Laporan Penjualan
        Gate::define('view-sales-report', function (User $user) {
            return $user->isGlobal() || 
                   ($user->isPusat() && $user->hasRole(['ASD', 'ACC'])) || 
                   ($user->isDealer() && $user->hasRole(['PC', 'KC']));
        });

        // Laporan Service
        Gate::define('view-service-report', function (User $user) {
            return $user->isGlobal() || 
                   ($user->isPusat() && $user->hasRole(['ASD', 'ACC'])) || 
                   ($user->isDealer() && $user->hasRole(['PC', 'KC']));
        });

        // Jurnal Pembelian
        Gate::define('view-purchase-journal', function (User $user) {
            return $user->isGlobal() || 
                   ($user->isPusat() && $user->hasRole(['IMS', 'ACC'])) || // Only PO Dealer
                   ($user->isGudang() && $user->hasRole(['AG', 'KG']));    // Only PO Supplier
        });

        // Laporan Nilai Persediaan
        Gate::define('view-inventory-value-report', function (User $user) {
            return $user->isGlobal() || $user->isGudang() || $user->isPusat() 
            // || $user->isDealer()
            ;
        });

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