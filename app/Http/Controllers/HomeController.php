<?php

namespace App\Http\Controllers;

use App\Models\Lokasi;
use App\Models\Barang;
use App\Models\Penjualan;
use App\Models\PurchaseOrder;
use App\Models\Receiving;
use App\Models\SalesTarget;
use App\Models\StockAdjustment;
use App\Models\StockMutation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $user = Auth::user();
        $role = $user->jabatan->singkatan ?? 'KSR';

        $viewName = 'dashboards._default';
        $data = [];

        switch ($role) {
            case 'SA':
            case 'PIC':
            case 'MA':
            case 'ACC':
                $viewName = 'dashboards._superadmin';
                $data = $this->getSuperAdminData();
                break;

            case 'SMD':
                $viewName = 'dashboards._superadmin';
                $data = $this->getServiceMdData($user);
                break;

            case 'KG':
            case 'KC':
                $viewName = 'dashboards._approver';
                $data = $this->getApproverData($user);
                break;

            case 'AG':
            case 'AD':
                $viewName = 'dashboards._operator';
                $data = $this->getOperatorData($user);
                break;

            case 'SLS':
                // CS dipindah ke default
                $viewName = 'dashboards._sales';
                $data = $this->getSalesData($user);
                break;

            case 'CS': // ++ PERUBAHAN: CS pakai default ++
            case 'KSR':
                $viewName = 'dashboards._default';
                break;
        }

        return view('home', compact('viewName', 'data'));
    }

    private function getSuperAdminData()
    {
        // ... (Kode tetap sama seperti sebelumnya) ...
        $widget1Value = Penjualan::whereDate('created_at', today())->count();
        $widget1Title = 'Penjualan Hari Ini';
        $widget1Route = route('admin.penjualans.index');
        $widget1Icon  = 'fas fa-cash-register';
        $widget1Color = 'bg-info';

        $stockValue = DB::table('inventory_batches')
            ->join('barangs', 'inventory_batches.barang_id', '=', 'barangs.id')
            ->sum(DB::raw('inventory_batches.quantity * COALESCE(barangs.selling_in, 0)'));

        $criticalStockParts = Barang::select(
                'barangs.part_name',
                'barangs.part_code',
                'barangs.stok_minimum',
                'barangs.merk',
                DB::raw('COALESCE(SUM(inventory_batches.quantity), 0) as total_stock')
            )
            ->leftJoin('inventory_batches', 'barangs.id', '=', 'inventory_batches.barang_id')
            ->groupBy('barangs.id', 'barangs.part_name', 'barangs.part_code', 'barangs.stok_minimum', 'barangs.merk')
            ->havingRaw('total_stock < barangs.stok_minimum AND barangs.stok_minimum > 0')
            ->limit(10)
            ->get();

        $salesData = Penjualan::select(DB::raw('DATE(tanggal_jual) as date'), DB::raw('SUM(total_harga) as total'))
            ->where('tanggal_jual', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        $salesChartLabels = $salesData->pluck('date')->map(fn ($date) => Carbon::parse($date)->format('d M'));
        $salesChartData = $salesData->pluck('total');

        return compact(
            'widget1Value', 'widget1Title', 'widget1Route', 'widget1Icon', 'widget1Color',
            'stockValue', 'criticalStockParts', 'salesChartLabels', 'salesChartData'
        );
    }

    private function getServiceMdData($user)
    {
        // ... (Kode tetap sama seperti sebelumnya) ...
        $pendingPOCount = PurchaseOrder::where('created_by', $user->id)
            ->where('status', 'PENDING_APPROVAL')
            ->count();

        $widget1Value = $pendingPOCount;
        $widget1Title = 'Request PO Pending';
        $widget1Route = route('admin.purchase-orders.index');
        $widget1Icon  = 'fas fa-file-invoice';
        $widget1Color = 'bg-warning';

        $stockValue = DB::table('inventory_batches')
            ->join('barangs', 'inventory_batches.barang_id', '=', 'barangs.id')
            ->sum(DB::raw('inventory_batches.quantity * COALESCE(barangs.selling_in, 0)'));

        $criticalStockParts = Barang::select(
                'barangs.part_name',
                'barangs.part_code',
                'barangs.stok_minimum',
                'barangs.merk',
                DB::raw('COALESCE(SUM(inventory_batches.quantity), 0) as total_stock')
            )
            ->leftJoin('inventory_batches', 'barangs.id', '=', 'inventory_batches.barang_id')
            ->groupBy('barangs.id', 'barangs.part_name', 'barangs.part_code', 'barangs.stok_minimum', 'barangs.merk')
            ->havingRaw('total_stock < barangs.stok_minimum AND barangs.stok_minimum > 0')
            ->limit(50)
            ->get();

        $salesChartLabels = [];
        $salesChartData = [];

        return compact(
            'widget1Value', 'widget1Title', 'widget1Route', 'widget1Icon', 'widget1Color',
            'stockValue', 'criticalStockParts', 'salesChartLabels', 'salesChartData'
        );
    }

    private function getApproverData($user)
    {
        $lokasiId = $user->lokasi_id;
        $lokasi = Lokasi::find($lokasiId);

        // ++ PERUBAHAN: Hapus Pending PO dari sini karena KG sudah tidak approve PO ++
        $pendingPOs = collect();

        $pendingAdjustments = StockAdjustment::where('status', 'PENDING_APPROVAL')
                                             ->where('lokasi_id', $lokasiId)
                                             ->with('barang')
                                             ->latest()
                                             ->take(5)
                                             ->get();

        $pendingMutations = StockMutation::where('status', 'PENDING_APPROVAL')
                                         ->where('lokasi_asal_id', $lokasiId)
                                         ->with('barang', 'lokasiTujuan')
                                         ->latest()
                                         ->take(5)
                                         ->get();

        return compact('pendingPOs', 'pendingAdjustments', 'pendingMutations', 'lokasi');
    }

    private function getOperatorData($user)
    {
        $lokasiId = $user->lokasi_id;
        $lokasi = Lokasi::find($lokasiId);
        $isPusat = $lokasi && $lokasi->tipe === 'PUSAT';

        $taskCounts = [
            'receiving_po' => 0,
            'qc' => 0,
            'putaway' => 0,
            'receiving_mutation' => 0,
            'pending_po_approval' => 0, // Tambahkan key ini
        ];

        // Admin Gudang Pusat (AG)
        if ($isPusat) {
            // ++ PERUBAHAN: Admin Gudang Pusat sekarang punya tugas Approval PO ++
            $taskCounts['pending_po_approval'] = PurchaseOrder::where('status', 'PENDING_APPROVAL')->count();
        }
        // Admin Dealer (AD)
        else {
            $taskCounts['receiving_po'] = PurchaseOrder::where('lokasi_id', $lokasiId)
                ->whereIn('status', ['APPROVED', 'PARTIALLY_RECEIVED'])
                ->count();

            $taskCounts['qc'] = Receiving::where('lokasi_id', $lokasiId)
                ->where('status', 'PENDING_QC')
                ->count();

            $taskCounts['putaway'] = Receiving::where('lokasi_id', $lokasiId)
                ->where('status', 'PENDING_PUTAWAY')
                ->count();
        }

        $taskCounts['receiving_mutation'] = StockMutation::where('lokasi_tujuan_id', $lokasiId)
            ->where('status', 'IN_TRANSIT')
            ->count();

        return compact('taskCounts', 'lokasi', 'isPusat');
    }

    private function getSalesData($user) {
        // ... (Kode tetap sama) ...
        $salesTarget = SalesTarget::where('user_id', $user->id)
            ->where('bulan', now()->month)
            ->where('tahun', now()->year)
            ->first();

        $targetAmount = $salesTarget ? $salesTarget->target_amount : 0;

        $achievedAmount = Penjualan::where('sales_id', $user->id)
            ->whereMonth('tanggal_jual', now()->month)
            ->whereYear('tanggal_jual', now()->year)
            ->sum('total_harga');

        $achievementPercentage = ($targetAmount > 0) ? (($achievedAmount / $targetAmount) * 100) : 0;

        $jumlahInsentif = 0;
        if ($achievementPercentage >= 100) {
            $jumlahInsentif = $achievedAmount * 0.02;
        } elseif ($achievementPercentage >= 80) {
            $jumlahInsentif = $achievedAmount * 0.01;
        }

        $recentSales = Penjualan::where('sales_id', $user->id)
            ->latest()
            ->limit(5)
            ->get();

        return compact('targetAmount', 'achievedAmount', 'achievementPercentage', 'jumlahInsentif', 'recentSales');
    }
}
