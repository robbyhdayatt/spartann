<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Contracts\Events\Dispatcher;
use JeroenNoten\LaravelAdminLte\Events\BuildingMenu;
use App\Models\PurchaseOrder;
use App\Models\Service;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(Dispatcher $events)
    {
        Blade::directive('rupiah', function ($expression) {
            return "<?php echo 'Rp ' . number_format($expression, 0, ',', '.'); ?>";
        });

        // Event Listener AdminLTE BuildingMenu untuk Notifikasi Dinamis Sidebar
        $events->listen(BuildingMenu::class, function (BuildingMenu $event) {
            if (!Schema::hasTable('purchase_orders') || !Schema::hasTable('services')) {
                return;
            }

            // Hitung PO Pending Approval (Cache 60s untuk performa tinggi)
            $pendingPoCount = Cache::remember('menu_pending_po_count', 60, function () {
                return PurchaseOrder::where('status', 'PENDING')->count();
            });

            if ($pendingPoCount > 0) {
                $event->menu->addAfter('admin.home', [
                    'text'        => 'PO Pending Approval',
                    'url'         => 'admin/purchase-orders',
                    'icon'        => 'fas fa-fw fa-clock text-warning',
                    'label'       => $pendingPoCount,
                    'label_color' => 'warning',
                    'can'         => 'approve-po',
                ]);
            }
        });
    }
}
