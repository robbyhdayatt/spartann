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
use App\Models\StockMovement;
use App\Models\InventoryBatch;
use App\Models\User;

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
                $viewName = 'dashboards._superadmin';
                $data = $this->getSuperAdminData();
                break;

            case 'SMD':
            case 'ASD': 
                $viewName = 'dashboards._sales'; 
                $data = $this->getServiceMdData($user);
                break;

            case 'ACC':
                $viewName = 'dashboards._accounting';
                $data = $this->getAccountingData();
                break;

            case 'PIC':
            case 'MA': 
                $viewName = 'dashboards._pic';
                $data = $this->getPicData();
                break;

            case 'KG':
            case 'KC':
                $viewName = 'dashboards._approver';
                $data = $this->getApproverData($user);
                break;

            case 'AG':
            case 'PC': 
                $viewName = 'dashboards._operator';
                $data = $this->getOperatorData($user);
                break;

            case 'KSR': 
                $viewName = 'dashboards._kasir';
                $data = $this->getKasirData($user);
                break;

            case 'SLS':
                $viewName = 'dashboards._sales';
                $data = $this->getSalesData($user);
                break;
        }

        return view('home', compact('viewName', 'data'));
    }

    // --- SUPER ADMIN (GOD MODE: MERGE ALL DATA) ---
    private function getSuperAdminData()
    {
        // 1. DATA IT & SYSTEM HEALTH (Original SA)
        $itData = [
            'todaySalesCount' => Penjualan::whereDate('created_at', today())->count(),
            'todayMovements' => StockMovement::whereDate('created_at', today())->count(),
            'totalUsers' => User::where('is_active', 1)->count(),
            'totalWarehouses' => Lokasi::count(),
            'negativeStockCount' => DB::table('inventory_batches')->where('quantity', '<', 0)->count(),
            'recentActivities' => StockMovement::with(['user', 'lokasi', 'barang'])->latest()->limit(10)->get(),
            'dbStats' => [
                'penjualan' => DB::table('penjualans')->count(),
                'stock_movements' => DB::table('stock_movements')->count(),
                'inventory_batches' => DB::table('inventory_batches')->count(),
            ]
        ];

        // 2. DATA OPERASIONAL GLOBAL (Manual Query untuk seluruh gudang)
        $opsData = [
            'globalReceivingPO' => PurchaseOrder::where('po_type', 'supplier_po')->whereIn('status', ['APPROVED', 'PARTIALLY_RECEIVED'])->count(),
            'globalPendingQC' => Receiving::where('status', 'PENDING_QC')->count(),
            'globalPendingPutaway' => Receiving::where('status', 'PENDING_PUTAWAY')->count(),
            'globalInTransit' => StockMutation::where('status', 'IN_TRANSIT')->count(),
            'criticalItems' => DB::table('inventory_batches')
                ->join('barangs', 'inventory_batches.barang_id', '=', 'barangs.id')
                ->select('barangs.part_name', 'barangs.part_code', DB::raw('SUM(inventory_batches.quantity) as total_qty'), 'barangs.stok_minimum')
                ->groupBy('barangs.id', 'barangs.part_name', 'barangs.part_code', 'barangs.stok_minimum')
                ->havingRaw('SUM(inventory_batches.quantity) < barangs.stok_minimum')
                ->limit(10)->get()
        ];

        // 3. DATA TOTAL ITEM TERJUAL GLOBAL (Sales + Service)
        // Hitung total item keluar dari Penjualan Detail
        $globalSalesQty = DB::table('penjualan_details')
            ->join('penjualans', 'penjualan_details.penjualan_id', '=', 'penjualans.id')
            ->whereMonth('penjualans.tanggal_jual', now()->month)
            ->sum('penjualan_details.qty_jual');
        
        // Hitung total item keluar dari Service (Convert Only)
        $validPartCodes = DB::table('converts_main')->distinct()->pluck('part_code')->toArray();
        $globalServiceQty = DB::table('service_details')
            ->join('services', 'service_details.service_id', '=', 'services.id')
            ->whereIn('service_details.item_code', $validPartCodes)
            ->whereMonth('services.created_at', now()->month)
            ->sum('service_details.quantity');

        $opsData['totalItemsSoldMonth'] = $globalSalesQty + $globalServiceQty;

        // 4. DATA FINANCE & MANAGEMENT (Reuse Function)
        // Kita panggil fungsi getAccountingData dan getPicData agar SA melihat angka yang sama persis
        $finData = $this->getAccountingData();
        $picData = $this->getPicData();

        // Gabungkan semua array menjadi satu
        return array_merge($itData, $opsData, $finData, $picData);
    }

    // --- ACCOUNTING (OMSET = SALES + SERVICE CONVERT) ---
    private function getAccountingData()
    {
        // Cache kode part convert
        $validPartCodes = DB::table('converts_main')->distinct()->pluck('part_code')->toArray();

        // --- A. OMSET (REVENUE) BULAN INI ---
        // 1. Penjualan Langsung (Retail Price)
        $salesRevenue = Penjualan::whereMonth('tanggal_jual', now()->month)
            ->whereYear('tanggal_jual', now()->year)
            ->sum('total_harga');

        // 2. Service Revenue (Barang Convert Only * Harga Retail)
        $serviceRevenue = DB::table('service_details')
            ->join('services', 'service_details.service_id', '=', 'services.id')
            ->join('barangs', 'service_details.item_code', '=', 'barangs.part_code')
            ->whereIn('service_details.item_code', $validPartCodes)
            ->whereMonth('services.created_at', now()->month)
            ->whereYear('services.created_at', now()->year)
            ->sum(DB::raw('service_details.quantity * COALESCE(barangs.retail, 0)'));

        $revenueThisMonth = $salesRevenue + $serviceRevenue;

        // --- B. NILAI ASET STOK (GLOBAL) ---
        // Basis: Selling Out (Harga Jual)
        $inventoryAssetValue = DB::table('inventory_batches')
            ->join('barangs', 'inventory_batches.barang_id', '=', 'barangs.id')
            ->sum(DB::raw('inventory_batches.quantity * COALESCE(barangs.selling_out, 0)'));

        // --- C. PROFIT (GROSS) BULAN INI ---
        // 1. Profit Penjualan (Retail - Selling Out)
        // Hitung HPP Penjualan
        $salesHPP = DB::table('penjualan_details')
            ->join('penjualans', 'penjualan_details.penjualan_id', '=', 'penjualans.id')
            ->join('barangs', 'penjualan_details.barang_id', '=', 'barangs.id')
            ->whereMonth('penjualans.tanggal_jual', now()->month)
            ->whereYear('penjualans.tanggal_jual', now()->year)
            ->sum(DB::raw('penjualan_details.qty_jual * COALESCE(barangs.selling_out, 0)'));
        
        $salesProfit = $salesRevenue - $salesHPP;

        // 2. Profit Service (Retail - Selling Out)
        $serviceProfit = DB::table('service_details')
            ->join('services', 'service_details.service_id', '=', 'services.id')
            ->join('barangs', 'service_details.item_code', '=', 'barangs.part_code')
            ->whereIn('service_details.item_code', $validPartCodes)
            ->whereMonth('services.created_at', now()->month)
            ->whereYear('services.created_at', now()->year)
            ->sum(DB::raw('(service_details.quantity * COALESCE(barangs.retail, 0)) - (service_details.quantity * COALESCE(barangs.selling_out, 0))'));

        $profitThisMonth = $salesProfit + $serviceProfit;

        // --- D. TREN PENJUALAN (CHART) 30 HARI ---
        // Ambil Data Harian Penjualan
        $dailySales = Penjualan::select(
                DB::raw('DATE(tanggal_jual) as date'), 
                DB::raw('SUM(total_harga) as total')
            )
            ->where('tanggal_jual', '>=', now()->subDays(30))
            ->groupBy('date')
            ->pluck('total', 'date')->toArray();

        // Ambil Data Harian Service (Convert Only)
        $dailyService = DB::table('service_details')
            ->join('services', 'service_details.service_id', '=', 'services.id')
            ->join('barangs', 'service_details.item_code', '=', 'barangs.part_code')
            ->whereIn('service_details.item_code', $validPartCodes)
            ->where('services.created_at', '>=', now()->subDays(30))
            ->select(
                DB::raw('DATE(services.created_at) as date'),
                DB::raw('SUM(service_details.quantity * COALESCE(barangs.retail, 0)) as total')
            )
            ->groupBy('date')
            ->pluck('total', 'date')->toArray();

        // Merge Data Chart
        $chartLabels = [];
        $chartData = [];
        
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $displayDate = now()->subDays($i)->format('d M');
            
            $valSales = $dailySales[$date] ?? 0;
            $valService = $dailyService[$date] ?? 0;
            
            $chartLabels[] = $displayDate;
            $chartData[] = $valSales + $valService;
        }

        // --- E. TRANSAKSI TERAKHIR ---
        $recentTransactions = Penjualan::with(['lokasi', 'sales'])->latest()->limit(10)->get();

        return compact(
            'revenueThisMonth', 
            'inventoryAssetValue', 
            'profitThisMonth', 
            'chartLabels', 
            'chartData', 
            'recentTransactions'
        );
    }

    // --- PIC / MANAGER ---
    private function getPicData()
    {
        // 1. Pending Approvals
        $pendingPO = PurchaseOrder::where('status', 'PENDING_APPROVAL')->count();
        $pendingMutasi = StockMutation::where('status', 'PENDING_APPROVAL')->count();
        $pendingAdjustment = StockAdjustment::where('status', 'PENDING_APPROVAL')->count();
        $totalPending = $pendingPO + $pendingMutasi + $pendingAdjustment;

        // 2. Ringkasan Operasional
        $totalCabang = Lokasi::where('tipe', '!=', 'PUSAT')->count();
        $totalUser = User::where('is_active', true)->count();

        // 3. Stok Kritis Global
        $criticalItems = DB::table('inventory_batches')
            ->join('barangs', 'inventory_batches.barang_id', '=', 'barangs.id')
            ->select('barangs.part_name', 'barangs.part_code', DB::raw('SUM(inventory_batches.quantity) as total_qty'), 'barangs.stok_minimum')
            ->groupBy('barangs.id', 'barangs.part_name', 'barangs.part_code', 'barangs.stok_minimum')
            ->havingRaw('SUM(inventory_batches.quantity) < barangs.stok_minimum')
            ->limit(5)->get();

        // 4. TOP CABANG BY OMSET (Sales + Service Convert)
        
        // A. Persiapan Data
        $validPartCodes = DB::table('converts_main')->distinct()->pluck('part_code')->toArray();
        $targetMonth = now()->month;
        $targetYear = now()->year;

        // B. Ambil Omset Penjualan per Lokasi
        $salesByLoc = DB::table('penjualans')
            ->whereMonth('tanggal_jual', $targetMonth)
            ->whereYear('tanggal_jual', $targetYear)
            ->select('lokasi_id', DB::raw('SUM(total_harga) as total'))
            ->groupBy('lokasi_id')
            ->pluck('total', 'lokasi_id'); // Output: [lokasi_id => total, ...]

        // C. Ambil Omset Service (Convert Only) per Lokasi
        $serviceByLoc = DB::table('service_details')
            ->join('services', 'service_details.service_id', '=', 'services.id')
            ->join('barangs', 'service_details.item_code', '=', 'barangs.part_code')
            ->whereIn('service_details.item_code', $validPartCodes)
            ->whereMonth('services.created_at', $targetMonth)
            ->whereYear('services.created_at', $targetYear)
            ->select('services.lokasi_id', DB::raw('SUM(service_details.quantity * COALESCE(barangs.retail, 0)) as total'))
            ->groupBy('services.lokasi_id')
            ->pluck('total', 'lokasi_id');

        // D. Gabungkan & Urutkan
        $allLocations = Lokasi::where('tipe', '!=', 'PUSAT')->get();
        
        $topCabang = $allLocations->map(function($lokasi) use ($salesByLoc, $serviceByLoc) {
            $salesTotal = $salesByLoc[$lokasi->id] ?? 0;
            $serviceTotal = $serviceByLoc[$lokasi->id] ?? 0;
            
            return (object) [
                'nama_lokasi' => $lokasi->nama_lokasi,
                'omset' => $salesTotal + $serviceTotal
            ];
        })
        ->sortByDesc('omset') // Urutkan Omset Tertinggi
        ->take(5); // Ambil Top 5

        return compact('pendingPO', 'pendingMutasi', 'pendingAdjustment', 'totalPending', 'totalCabang', 'totalUser', 'criticalItems', 'topCabang');
    }

    // --- OPERATOR / PART COUNTER ---
    private function getOperatorData($user)
    {
        $lokasiId = $user->lokasi_id;
        $isPusat = false;
        
        if ($user->jabatan->singkatan === 'SA' || ($user->lokasi && $user->lokasi->tipe === 'PUSAT')) {
            $isPusat = true;
        }

        $taskCounts = [
            'receiving_po' => 0, 'qc' => 0, 'putaway' => 0, 
            'dealer_request_approval' => 0, 'incoming_mutation_transit' => 0
        ];
        $stockData = collect([]);
        $totalItemsSoldMonth = 0;

        if ($isPusat || !$lokasiId) { 
            // Pusat Logic
            $taskCounts['dealer_request_approval'] = PurchaseOrder::where('status', 'PENDING_APPROVAL')->where('po_type', 'dealer_request')->count();
        } else {
            // Dealer Logic
            $taskCounts['receiving_po'] = PurchaseOrder::where('lokasi_id', $lokasiId)
                ->where('po_type', 'dealer_request')->whereIn('status', ['APPROVED', 'PARTIALLY_RECEIVED'])->count();
            $taskCounts['incoming_mutation_transit'] = StockMutation::where('lokasi_tujuan_id', $lokasiId)->where('status', 'IN_TRANSIT')->count();
            
            $stockData = DB::table('inventory_batches')
                ->join('barangs', 'inventory_batches.barang_id', '=', 'barangs.id')
                ->where('inventory_batches.lokasi_id', $lokasiId)
                ->select('barangs.part_name', 'barangs.part_code', 'barangs.stok_minimum', DB::raw('SUM(inventory_batches.quantity) as total_qty'))
                ->groupBy('barangs.id', 'barangs.part_name', 'barangs.part_code', 'barangs.stok_minimum')
                ->orderByRaw('(SUM(inventory_batches.quantity) < barangs.stok_minimum) DESC')
                ->limit(10)->get();

            // Total Item Terjual (Dealer Scope)
            $validPartCodes = DB::table('converts_main')->distinct()->pluck('part_code')->toArray();
            
            $itemsFromSales = DB::table('penjualan_details')
                ->join('penjualans', 'penjualan_details.penjualan_id', '=', 'penjualans.id')
                ->where('penjualans.lokasi_id', $lokasiId)
                ->whereMonth('penjualans.tanggal_jual', now()->month)->sum('penjualan_details.qty_jual');

            $itemsFromService = DB::table('service_details')
                ->join('services', 'service_details.service_id', '=', 'services.id')
                ->where('services.lokasi_id', $lokasiId)
                ->whereMonth('services.created_at', now()->month)
                ->whereIn('service_details.item_code', $validPartCodes)
                ->sum('service_details.quantity');

            $totalItemsSoldMonth = $itemsFromSales + $itemsFromService;
        }

        if($lokasiId) {
            $taskCounts['qc'] = Receiving::where('lokasi_id', $lokasiId)->where('status', 'PENDING_QC')->count();
            $taskCounts['putaway'] = Receiving::where('lokasi_id', $lokasiId)->where('status', 'PENDING_PUTAWAY')->count();
        }

        $lokasi = $lokasiId ? Lokasi::find($lokasiId) : (object)['nama_lokasi' => 'Global/Pusat'];

        return compact('taskCounts', 'lokasi', 'isPusat', 'stockData', 'totalItemsSoldMonth');
    }

    // --- KASIR ---
    private function getKasirData($user)
    {
        $lokasiId = $user->lokasi_id;
        $serviceToday = DB::table('services')->where('lokasi_id', $lokasiId)->whereDate('created_at', today())->count();
        $serviceWeek = DB::table('services')->where('lokasi_id', $lokasiId)->whereBetween('created_at', [now()->subDays(7), now()])->count();
        $salesToday = Penjualan::where('lokasi_id', $lokasiId)->whereDate('tanggal_jual', today())->count();
        $salesWeek = Penjualan::where('lokasi_id', $lokasiId)->whereBetween('tanggal_jual', [now()->subDays(7), now()])->count();

        $validPartCodes = DB::table('converts_main')->distinct()->pluck('part_code')->toArray();
        $itemsFromSales = DB::table('penjualan_details')
            ->join('penjualans', 'penjualan_details.penjualan_id', '=', 'penjualans.id')
            ->where('penjualans.lokasi_id', $lokasiId)
            ->whereMonth('penjualans.tanggal_jual', now()->month)->sum('penjualan_details.qty_jual');
        $itemsFromService = DB::table('service_details')
            ->join('services', 'service_details.service_id', '=', 'services.id')
            ->where('services.lokasi_id', $lokasiId)
            ->whereMonth('services.created_at', now()->month)
            ->whereIn('service_details.item_code', $validPartCodes)->sum('service_details.quantity');

        $totalItemsSoldMonth = $itemsFromSales + $itemsFromService;

        return compact('serviceToday', 'serviceWeek', 'salesToday', 'salesWeek', 'totalItemsSoldMonth', 'lokasiId');
    }

    // --- SALES / SMD ---
    private function getServiceMdData($user)
    {
        $myRequests = PurchaseOrder::where('created_by', $user->id)->where('po_type', 'dealer_request')->latest()->take(5)->get();
        $stockData = DB::table('inventory_batches')
            ->join('barangs', 'inventory_batches.barang_id', '=', 'barangs.id')
            ->join('lokasi', 'inventory_batches.lokasi_id', '=', 'lokasi.id')
            ->where('lokasi.tipe', '!=', 'PUSAT')
            ->select('lokasi.nama_lokasi', 'barangs.part_name', 'barangs.part_code', 'barangs.stok_minimum', DB::raw('SUM(inventory_batches.quantity) as total_qty'))
            ->groupBy('lokasi.id', 'lokasi.nama_lokasi', 'barangs.id', 'barangs.part_name', 'barangs.part_code', 'barangs.stok_minimum')
            ->orderByRaw('(SUM(inventory_batches.quantity) < barangs.stok_minimum) DESC')
            ->orderBy('lokasi.nama_lokasi')->limit(20)->get();
        $totalStokCount = DB::table('inventory_batches')->join('lokasi', 'inventory_batches.lokasi_id', '=', 'lokasi.id')->where('lokasi.tipe', '!=', 'PUSAT')->sum('quantity');

        return ['targetAmount' => 0, 'achievedAmount' => 0, 'achievementPercentage' => 0, 'jumlahInsentif' => 0, 'recentSales' => collect([]), 'myRequests' => $myRequests, 'totalStokCount' => $totalStokCount, 'stockData' => $stockData, 'isSMD' => true];
    }

    private function getApproverData($user)
    {
        $lokasiId = $user->lokasi_id;
        $lokasi = Lokasi::find($lokasiId);
        $pendingSupplierPOs = PurchaseOrder::where('status', 'PENDING_APPROVAL')->where('po_type', 'supplier_po')->where('lokasi_id', $lokasiId)->with('supplier', 'createdBy')->latest()->take(5)->get();
        $pendingAdjustments = StockAdjustment::where('status', 'PENDING_APPROVAL')->where('lokasi_id', $lokasiId)->with('barang')->latest()->take(5)->get();
        $pendingMutations = StockMutation::where('status', 'PENDING_APPROVAL')->where('lokasi_asal_id', $lokasiId)->with('barang', 'lokasiTujuan')->latest()->take(5)->get();
        return compact('pendingSupplierPOs', 'pendingAdjustments', 'pendingMutations', 'lokasi');
    }

    private function getSalesData($user)
    {
        $salesTarget = SalesTarget::where('user_id', $user->id)->where('bulan', now()->month)->where('tahun', now()->year)->first();
        $targetAmount = $salesTarget ? $salesTarget->target_amount : 0;
        $achievedAmount = Penjualan::where('sales_id', $user->id)->whereMonth('tanggal_jual', now()->month)->whereYear('tanggal_jual', now()->year)->sum('total_harga');
        $achievementPercentage = ($targetAmount > 0) ? (($achievedAmount / $targetAmount) * 100) : 0;
        $jumlahInsentif = ($achievementPercentage >= 100) ? $achievedAmount * 0.02 : (($achievementPercentage >= 80) ? $achievedAmount * 0.01 : 0);
        $recentSales = Penjualan::where('sales_id', $user->id)->latest()->limit(5)->get();
        return ['targetAmount' => $targetAmount, 'achievedAmount' => $achievedAmount, 'achievementPercentage' => $achievementPercentage, 'jumlahInsentif' => $jumlahInsentif, 'recentSales' => $recentSales, 'isSMD' => false];
    }
}