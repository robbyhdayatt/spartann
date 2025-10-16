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
        Gate::define('access-po-module', fn(User $user) => $user->hasRole(['KG', 'AG']));
        Gate::define('create-po', fn(User $user) => $user->hasRole('AG'));
        Gate::define('approve-po', function (User $user, $purchaseOrder) {
            return $user->hasRole('KG') && $user->lokasi && $user->lokasi->tipe === 'PUSAT' && $user->gudang_id === $purchaseOrder->gudang_id;
        });
        Gate::define('manage-purchase-returns', function(User $user) {
            // Hanya Admin Gudang di Gudang Pusat yang bisa melakukan retur
            return $user->hasRole('AG') && $user->lokasi && $user->lokasi->tipe === 'PUSAT';
        });

        // --- OPERASIONAL GUDANG & DEALER ---
        Gate::define('perform-warehouse-ops', fn(User $user) => $user->hasRole(['AG', 'AD']));
        Gate::define('create-stock-adjustment', fn(User $user) => $user->hasRole(['AG', 'AD']));
        Gate::define('manage-quarantine-stock', fn(User $user) => $user->hasRole(['AG', 'AD']));
        Gate::define('create-stock-transaction', fn(User $user) => $user->hasRole(['AG', 'AD']));
        Gate::define('approve-stock-transaction', function (User $user, $transaction) {
            $lokasiId = $transaction->gudang_id ?? $transaction->gudang_asal_id;
            if (!$user->gudang_id) return false;

            if ($user->hasRole('KG') || $user->hasRole('KC')) {
                return $user->gudang_id === $lokasiId;
            }
            return false;
        });

        Gate::define('approve-stock-adjustment', function (User $user, $stockAdjustment) {
            // Pastikan user memiliki lokasi dan adjustment memiliki lokasi
            if (!$user->gudang_id || !$stockAdjustment->gudang_id) {
                return false;
            }

            // Cek apakah user adalah Kepala Gudang atau Kepala Cabang
            // dan apakah lokasi mereka sama dengan lokasi adjustment
            if ($user->hasRole(['KG', 'KC'])) {
                return $user->gudang_id === $stockAdjustment->gudang_id;
            }

            return false;
        });

        // Izin untuk MELIHAT Penerimaan Mutasi (KG/KC juga bisa)
        Gate::define('view-mutation-receiving', fn(User $user) => $user->hasRole(['AG', 'AD', 'KG', 'KC']));
        // Izin untuk MEMPROSES Penerimaan Mutasi (Hanya AG/AD)
        Gate::define('receive-mutation', fn(User $user) => $user->hasRole(['AG', 'AD']));
        
        // Izin untuk MELIHAT Stok Karantina (KG/KC juga bisa)
        Gate::define('view-quarantine-stock', fn(User $user) => $user->hasRole(['AG', 'AD', 'KG', 'KC']));

        // --- PENJUALAN ---
        Gate::define('access-sales-module', fn(User $user) => $user->hasRole(['SLS', 'KSR', 'CS']));

        // Izin untuk melihat data penjualan (tetap izinkan Kasir)
        Gate::define('view-sales', function(User $user) {
            return $user->hasRole(['SA', 'PIC', 'MA', 'KC', 'SLS', 'CS', 'KSR']);
        });
        
        // PERUBAHAN DI SINI: Hapus 'KSR' dari daftar
        // Izin untuk membuat transaksi penjualan baru (Sales dan Counter Service saja)
        Gate::define('create-sale', function(User $user) {
            return $user->hasRole(['SLS', 'CS']);
        });
        
        // Izin khusus untuk kasir yang mungkin hanya bisa cetak
        Gate::define('print-invoice-only', function(User $user) {
            return $user->hasRole('KSR');
        });

        // --- LAPORAN & MARKETING ---
        Gate::define('view-reports', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA', 'KG', 'KC']));
        Gate::define('manage-marketing', fn(User $user) => $user->hasRole(['SA', 'PIC', 'MA']));

        // --- AKSES KHUSUS KEPALA CABANG (READ-ONLY) ---
        Gate::define('is-read-only', fn(User $user) => $user->hasRole('KC'));

        // --- SERVICE ---
        // Izin untuk melihat daftar/detail service (bisa juga untuk manajer)
        Gate::define('view-service', function(User $user) {
            return $user->hasRole(['SA', 'PIC', 'MA', 'KC', 'CS', 'KSR']);
        });

        // Izin untuk membuat/mengelola transaksi service (hanya Counter Service)
        Gate::define('manage-service', function(User $user) {
            return $user->hasRole('CS');
        });
    }
}
