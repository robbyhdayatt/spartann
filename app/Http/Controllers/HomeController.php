<?php

namespace App\Http\Controllers;

use App\Models\Lokasi;
use App\Models\Barang; // GANTI PART JADI BARANG
use App\Models\Penjualan;
use App\Models\PurchaseOrder;
use App\Models\Receiving;
use App\Models\SalesTarget;
use App\Models\StockAdjustment;
use App\Models\StockMutation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Incentive;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $user = Auth::user();
        $role = $user->jabatan->singkatan ?? 'KSR'; // Fallback jika jabatan null

        $viewName = 'dashboards._default';
        $data = [];

        switch ($role) {
            case 'SA':
            case 'PIC':
            case 'MA':
                $viewName = 'dashboards._superadmin';
                $data = $this->getSuperAdminData();
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
                $viewName = 'dashboards._sales';
                $data = $this->getSalesData($user);
                break;
        }

        return view('home', compact('viewName', 'data'));
    }

    private function getSuperAdminData()
    {
        $salesToday = Penjualan::whereDate('created_at', today())->count();

        // PERBAIKAN QUERY 1: Nilai Stok
        // Menggunakan tabel 'barangs' dan 'inventory_batches.barang_id'
        // Menggunakan 'selling_in' sebagai nilai valuasi aset
        $stockValue = DB::table('inventory_batches')
            ->join('barangs', 'inventory_batches.barang_id', '=', 'barangs.id')
            ->sum(DB::raw('inventory_batches.quantity * barangs.selling_in'));

        // PERBAIKAN QUERY 2: Stok Kritis
        // Menggunakan kolom baru: part_name, part_code, stok_minimum
        $criticalStockParts = Barang::select(
                'barangs.part_name',
                'barangs.part_code',
                'barangs.stok_minimum',
                DB::raw('SUM(inventory_batches.quantity) as total_stock')
            )
            ->join('inventory_batches', 'barangs.id', '=', 'inventory_batches.barang_id')
            ->groupBy('barangs.id', 'barangs.part_name', 'barangs.part_code', 'barangs.stok_minimum')
            ->havingRaw('total_stock < barangs.stok_minimum AND barangs.stok_minimum > 0')
            ->get();

        $salesData = Penjualan::select(DB::raw('DATE(tanggal_jual) as date'), DB::raw('SUM(total_harga) as total'))
            ->where('tanggal_jual', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        $salesChartLabels = $salesData->pluck('date')->map(fn ($date) => Carbon::parse($date)->format('d M'));
        $salesChartData = $salesData->pluck('total');

        return compact('salesToday', 'stockValue', 'criticalStockParts', 'salesChartLabels', 'salesChartData');
    }

    private function getApproverData($user)
    {
        $lokasiId = $user->lokasi_id;
        $lokasi = Lokasi::find($lokasiId);

        $pendingPurchaseOrders = [];
        // Cek apakah KG (Kepala Gudang)
        if ($user->jabatan->singkatan === 'KG') {
             $pendingPurchaseOrders = PurchaseOrder::where('status', 'PENDING_APPROVAL')
                                                  ->where('lokasi_id', $lokasiId)
                                                  ->with('supplier', 'sumberLokasi') // Eager load
                                                  ->get();
        }

        $pendingAdjustments = StockAdjustment::where('status', 'PENDING_APPROVAL')
                                             ->where('lokasi_id', $lokasiId)
                                             ->with('barang') // Eager load barang
                                             ->get();

        $pendingMutations = StockMutation::where('status', 'PENDING_APPROVAL')
                                         ->where('lokasi_asal_id', $lokasiId)
                                         ->with('barang') // Eager load barang
                                         ->get();

        return compact('pendingPurchaseOrders', 'pendingAdjustments', 'pendingMutations', 'lokasi');
    }

    private function getOperatorData($user)
    {
        $lokasiId = $user->lokasi_id;
        $lokasi = Lokasi::find($lokasiId);
        $isPusat = $lokasi && $lokasi->tipe === 'PUSAT';

        $taskCounts = [];
        if ($isPusat) {
            $taskCounts['pending_receiving_po'] = PurchaseOrder::where('lokasi_id', $lokasiId)
                ->whereIn('status', ['APPROVED', 'PARTIALLY_RECEIVED'])
                ->count();
            $taskCounts['pending_qc'] = Receiving::where('lokasi_id', $lokasiId)
                ->where('status', 'PENDING_QC')
                ->count();
            $taskCounts['pending_putaway'] = Receiving::where('lokasi_id', $lokasiId)
                ->where('status', 'PENDING_PUTAWAY')
                ->count();
        }

        $taskCounts['pending_receiving_mutation'] = StockMutation::where('lokasi_tujuan_id', $lokasiId)
            ->where('status', 'IN_TRANSIT')
            ->count();

        return compact('taskCounts', 'lokasi', 'isPusat');
    }

    private function getSalesData($user)
    {
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
        $incentiveAmount = $jumlahInsentif;

        $recentSales = Penjualan::where('sales_id', $user->id)->latest()->limit(5)->get();

        return compact('targetAmount', 'achievedAmount', 'achievementPercentage', 'incentiveAmount', 'recentSales');
    }
}
