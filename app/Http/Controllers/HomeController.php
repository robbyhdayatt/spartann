<?php

namespace App\Http\Controllers;

use App\Models\Lokasi;
use App\Models\Part;
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

    /**
     * Menampilkan dashboard aplikasi berdasarkan peran pengguna.
     */
    public function index()
    {
        $user = Auth::user();
        $role = $user->jabatan->singkatan;

        $viewName = 'dashboards._default';
        $data = [];

        switch ($role) {
            case 'SA':
            case 'PIC':
            case 'MA':
                $viewName = 'dashboards._superadmin';
                $data = $this->getSuperAdminData();
                break;

            case 'KG': // Kepala lokasi (Pusat)
            case 'KC': // Kepala Cabang (Dealer)
                $viewName = 'dashboards._approver'; // Kita akan buat view baru yg generik
                $data = $this->getApproverData($user);
                break;

            case 'AG': // Admin lokasi (Pusat)
            case 'AD': // Admin Dealer
                $viewName = 'dashboards._operator'; // View baru yg generik
                $data = $this->getOperatorData($user);
                break;

            case 'SLS': // Sales
            // case 'CS':  // Counter Sales
                $viewName = 'dashboards._sales';
                $data = $this->getSalesData($user);
                break;

            // KSR (Kasir) akan menggunakan _default view
        }

        return view('home', compact('viewName', 'data'));
    }

    /**
     * Data untuk dashboard level manajemen (Super Admin, PIC, Manajer).
     */
    private function getSuperAdminData()
    {
        $salesToday = Penjualan::whereDate('created_at', today())->count();
        $stockValue = DB::table('inventory_batches')
            ->join('parts', 'inventory_batches.part_id', '=', 'parts.id')
            ->sum(DB::raw('inventory_batches.quantity * parts.dpp'));

        $criticalStockParts = Part::select('parts.nama_part', 'parts.kode_part', 'parts.stok_minimum', DB::raw('SUM(inventory_batches.quantity) as total_stock'))
            ->join('inventory_batches', 'parts.id', '=', 'inventory_batches.part_id')
            ->groupBy('parts.id', 'parts.nama_part', 'parts.kode_part', 'parts.stok_minimum')
            ->havingRaw('total_stock < parts.stok_minimum AND parts.stok_minimum > 0')
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

    /**
     * Data untuk dashboard approver (Kepala lokasi & Kepala Cabang).
     */
    private function getApproverData($user)
    {
        $lokasiId = $user->lokasi_id;
        $lokasi = Lokasi::find($lokasiId);

        // Kepala lokasi melihat PO, Kepala Cabang tidak
        $pendingPurchaseOrders = [];
        if ($user->hasRole('KG')) {
             $pendingPurchaseOrders = PurchaseOrder::where('status', 'PENDING_APPROVAL')->where('lokasi_id', $lokasiId)->get();
        }

        $pendingAdjustments = StockAdjustment::where('status', 'PENDING_APPROVAL')->where('lokasi_id', $lokasiId)->get();
        $pendingMutations = StockMutation::where('status', 'PENDING_APPROVAL')->where('lokasi_asal_id', $lokasiId)->get();

        return compact('pendingPurchaseOrders', 'pendingAdjustments', 'pendingMutations', 'lokasi');
    }

    /**
     * Data untuk dashboard operator (Admin lokasi & Admin Dealer).
     */
    private function getOperatorData($user)
    {
        $lokasiId = $user->lokasi_id;
        $lokasi = Lokasi::find($lokasiId);
        $isPusat = $lokasi->tipe === 'PUSAT';

        $taskCounts = [];
        if ($isPusat) {
            // Tugas Admin lokasi Pusat
            $taskCounts['pending_receiving_po'] = PurchaseOrder::where('lokasi_id', $lokasiId)->whereIn('status', ['APPROVED', 'PARTIALLY_RECEIVED'])->count();
            $taskCounts['pending_qc'] = Receiving::where('lokasi_id', $lokasiId)->where('status', 'PENDING_QC')->count();
            $taskCounts['pending_putaway'] = Receiving::where('lokasi_id', $lokasiId)->where('status', 'PENDING_PUTAWAY')->count();
        }

        // Tugas bersama untuk Admin lokasi & Admin Dealer
        $taskCounts['pending_receiving_mutation'] = StockMutation::where('lokasi_tujuan_id', $lokasiId)->where('status', 'IN_TRANSIT')->count();

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
            ->sum('total_harga'); // Asumsi pencapaian berdasarkan total_harga, ganti ke subtotal jika perlu

        $achievementPercentage = ($targetAmount > 0) ? (($achievedAmount / $targetAmount) * 100) : 0;

        // ++ PERBAIKAN LOGIKA INSENTIF ++
        // Hitung insentif secara real-time, jangan baca dari database
        $jumlahInsentif = 0;
        if ($achievementPercentage >= 100) {
            $jumlahInsentif = $achievedAmount * 0.02; // 2%
        } elseif ($achievementPercentage >= 80) {
            $jumlahInsentif = $achievedAmount * 0.01; // 1%
        }
        // Gunakan variabel yang baru dihitung
        $incentiveAmount = $jumlahInsentif;
        // ++ AKHIR PERBAIKAN ++

        $recentSales = Penjualan::where('sales_id', $user->id)->latest()->limit(5)->get();

        return compact('targetAmount', 'achievedAmount', 'achievementPercentage', 'incentiveAmount', 'recentSales');
    }
}
