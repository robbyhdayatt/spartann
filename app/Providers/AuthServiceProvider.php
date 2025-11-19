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
        // 1. DEFINISI PERAN (ROLE HELPERS)
        // =================================================================
        Gate::define('is-super-admin', fn(User $user) => $user->hasRole('SA'));
        Gate::define('is-pic', fn(User $user) => $user->hasRole('PIC'));
        Gate::define('is-manager', fn(User $user) => $user->hasRole('MA'));
        Gate::define('is-pusat-staff', fn(User $user) => $user->hasRole(['KG', 'AG']));
        Gate::define('is-dealer-staff', fn(User $user) => $user->hasRole(['KC', 'AD', 'CS', 'KSR', 'SLS']));

        // =================================================================
        // 2. GATE MODUL PEMBELIAN (PO)
        // =================================================================

        // Create PO: HANYA Dealer UA22001. Pusat DIBLOKIR.
        Gate::define('create-po', function (User $user) {
            if ($user->hasRole(['SA', 'PIC'])) return true;

            // Cek lokasi user
            if (!$user->lokasi) return false;

            // BLOKIR PUSAT (Tidak boleh request ke diri sendiri)
            if ($user->lokasi->tipe === 'PUSAT') {
                return false;
            }

            // HANYA Admin Dealer 'UA22001' yang boleh request
            if ($user->hasRole(['AD', 'KC']) && $user->lokasi->kode_lokasi === 'UA22001') {
                return true;
            }

            return false;
        });

        // Approve PO: Kepala Gudang Pusat WAJIB bisa
        Gate::define('approve-po', function (User $user, $po = null) {
            if ($user->hasRole(['SA', 'PIC'])) return true;

            // Hanya Admin Gudang Pusat yang boleh approve
            if ($user->hasRole('AG') && $user->lokasi && $user->lokasi->tipe === 'PUSAT') {
                return true;
            }
            return false;
        });

        // Akses Menu PO: Gabungan create atau approve
        Gate::define('view-po-module', function ($user) {
             return $user->can('create-po') || $user->can('approve-po') || $user->hasRole(['SA', 'PIC', 'MA']);
        });

        Gate::define('manage-purchase-returns', function(User $user) {
            // Retur pembelian ke Supplier External (Biasanya Pusat yang urus)
            return $user->hasRole(['PIC', 'SA']) && $user->lokasi && $user->lokasi->tipe === 'PUSAT';
        });


        // =================================================================
        // 3. GATE MODUL INBOUND (PENERIMAAN)
        // =================================================================

        // Pusat DIBLOKIR TOTAL dari menu ini (Receiving, QC, Putaway)
        Gate::define('perform-warehouse-ops', function (User $user) {
            if ($user->hasRole(['SA', 'PIC'])) return true;
            if (!$user->lokasi) return false;

            // BLOKIR PUSAT
            if ($user->lokasi->tipe === 'PUSAT') {
                return false;
            }

            // Dealer (Admin & Kepala) DIPERBOLEHKAN
            if ($user->hasRole(['AG', 'AD', 'KG', 'KC'])) {
                return true;
            }

            return false;
        });

        // =================================================================
        // 4. GATE MODUL TRANSAKSI STOK (MUTASI & ADJUSTMENT)
        // =================================================================

        // Create Mutation: Pusat DIBLOKIR
        Gate::define('create-stock-transaction', function (User $user) {
            if ($user->hasRole(['SA', 'PIC'])) return true;
            if (!$user->lokasi) return false;

            // BLOKIR PUSAT
            if ($user->lokasi->tipe === 'PUSAT') return false;

            return $user->hasRole(['AG', 'AD', 'KG', 'KC']);
        });

        // Create Adjustment: Pusat DIBLOKIR
        Gate::define('create-stock-adjustment', function (User $user) {
            if ($user->hasRole(['SA', 'PIC'])) return true;
            if (!$user->lokasi) return false;

            // BLOKIR PUSAT
            if ($user->lokasi->tipe === 'PUSAT') return false;

            return $user->hasRole(['AG', 'AD', 'KG', 'KC']);
        });

        // Approval Transaksi (Kepala Cabang/Gudang boleh approve jika ada request)
        Gate::define('approve-stock-transaction', fn(User $user) => $user->hasRole(['SA', 'PIC', 'KG', 'KC']));
        Gate::define('approve-stock-adjustment', fn(User $user) => $user->hasRole(['SA', 'PIC', 'KG', 'KC']));

        // Gate untuk Menu Sidebar (Menyembunyikan menu jika tidak punya akses create/approve)
        Gate::define('view-stock-mutations-menu', function ($user) {
            // Pusat (Admin) tidak bisa lihat, tapi Kepala Pusat mungkin butuh lihat untuk approval
            // Namun karena instruksi "seluruh menu transaksi pusat ditiadakan", kita pakai create gate
            // Atau perbolehkan jika dia Approver
             return $user->can('create-stock-transaction') || $user->can('approve-stock-transaction') || $user->hasRole(['SA', 'PIC']);
        });

        Gate::define('view-stock-adjustments-menu', function ($user) {
             return $user->can('create-stock-adjustment') || $user->can('approve-stock-adjustment') || $user->hasRole(['SA', 'PIC']);
        });

        // Gate tambahan untuk menu spesifik
        Gate::define('view-mutation-receiving', fn(User $user) => $user->hasRole(['AG', 'AD', 'KG', 'KC', 'PIC', 'SA']));
        Gate::define('receive-mutation', fn(User $user) => $user->hasRole(['AG', 'AD']));
        Gate::define('view-quarantine-stock', function (User $user) {
            // Gunakan logika yang sama dengan inbound (Dealer Boleh, Pusat Tidak)
            return $user->can('perform-warehouse-ops');
        });

        // 2. Mengelola Stok Karantina (Proses/Retur/Musnahkan)
        Gate::define('manage-quarantine-stock', function (User $user) {
            return $user->can('perform-warehouse-ops');
        });


        // =================================================================
        // 5. GATE MASTER DATA & SETTINGS (EXISTING)
        // =================================================================
        Gate::define('manage-users', fn(User $user) => $user->hasRole('SA'));
        Gate::define('manage-locations', fn(User $user) => $user->hasRole(['SA', 'PIC']));
        Gate::define('view-master-data', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA']));
        Gate::define('manage-barangs', fn(User $user) => $user->hasRole(['SA', 'PIC', 'ASD']));
        Gate::define('manage-converts', fn(User $user) => $user->hasRole(['SA', 'PIC', 'ASD']));


        // =================================================================
        // 6. GATE PENJUALAN & SERVICE (EXISTING)
        // =================================================================
        Gate::define('access-sales-module', fn(User $user) => $user->hasRole(['SLS', 'KSR', 'CS']));
        Gate::define('view-sales', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA', 'KC', 'SLS', 'CS', 'KSR']));
        Gate::define('create-sale', fn(User $user) => $user->hasRole(['SLS', 'CS']));
        Gate::define('print-invoice-only', fn(User $user) => $user->hasRole('KSR'));

        Gate::define('view-service', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA', 'KC', 'CS', 'KSR']));
        Gate::define('manage-service', fn(User $user) => $user->hasRole('CS'));
        Gate::define('export-service-report', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA', 'KC', 'CS']));


        // =================================================================
        // 7. GATE LAPORAN (EXISTING)
        // =================================================================
        Gate::define('view-reports', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA', 'KG', 'KC']));
        Gate::define('view-global-reports', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA']));
        Gate::define('view-purchase-journal', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA', 'KG']));
        Gate::define('manage-marketing', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA']));
    }
}
