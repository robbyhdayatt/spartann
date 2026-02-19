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
use App\Models\Service;

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

            case 'ASD':
                $viewName = 'dashboards._asd';
                $data = $this->getAsdData($user);
                break;

            case 'IMS':
                $viewName = 'dashboards._ims';
                $data = $this->getServiceMdData($user);
                break;

            case 'ACC':
                $viewName = 'dashboards._accounting';
                $data = $this->getAccountingData();
                break;

            case 'PIC':
                $viewName = 'dashboards._pic';
                $data = $this->getPicData();
                break;

            case 'KG':
                $viewName = 'dashboards._approver';
                $data = $this->getApproverData($user);
                break;

            case 'KC':
                $viewName = 'dashboards._kepala_cabang';
                $data = $this->getKepalaCabangData($user);
                break;

            case 'AG':
                $viewName = 'dashboards._admin_gudang';
                $data = $this->getAdminGudangData($user);
                break;

            case 'PC':
                $viewName = 'dashboards._operator';
                $data = $this->getOperatorData($user);
                break;

            case 'KSR':
                $viewName = 'dashboards._kasir';
                $data = $this->getKasirData($user);
                break;
        }

        return view('home', compact('viewName', 'data'));
    }

    // --- SUPER ADMIN (MERGE DATA) ---
    private function getSuperAdminData()
    {
        // 1. IT & SYSTEM HEALTH
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

        // 2. OPS GLOBAL
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

        // 3. ITEM TERJUAL (Fisik)
        $validPartCodes = DB::table('converts_main')->distinct()->pluck('part_code')->toArray();
        $validBarangIds = Barang::whereIn('part_code', $validPartCodes)->pluck('id');

        $globalSalesQty = DB::table('penjualan_details')
            ->join('penjualans', 'penjualan_details.penjualan_id', '=', 'penjualans.id')
            ->whereMonth('penjualans.tanggal_jual', now()->month)
            ->sum('penjualan_details.qty_jual');
        
        $netServiceMovement = DB::table('stock_movements')
            ->where('referensi_type', 'like', '%Service%')
            ->whereIn('barang_id', $validBarangIds)
            ->whereMonth('created_at', now()->month)
            ->sum('jumlah');

        $opsData['totalItemsSoldMonth'] = $globalSalesQty + abs($netServiceMovement);

        // 4. FINANCE
        $finData = $this->getAccountingData();
        $picData = $this->getPicData();

        return array_merge($itData, $opsData, $finData, $picData);
    }

    // --- ACCOUNTING  ---
    private function getAccountingData()
    {
        $validPartCodes = DB::table('converts_main')->distinct()->pluck('part_code')->toArray();

        // -------------------------------------------------------------
        // A. HITUNG OMSET & PROFIT (PENJUALAN LANGSUNG)
        // -------------------------------------------------------------
        // Query Penjualan
        $salesData = DB::table('penjualan_details')
            ->join('penjualans', 'penjualan_details.penjualan_id', '=', 'penjualans.id')
            ->join('barangs', 'penjualan_details.barang_id', '=', 'barangs.id')
            ->whereMonth('penjualans.tanggal_jual', now()->month)
            ->whereYear('penjualans.tanggal_jual', now()->year)
            ->select(
                DB::raw('SUM(penjualan_details.subtotal) as omset'),
                DB::raw('SUM(penjualan_details.qty_jual * COALESCE(barangs.selling_out, 0)) as hpp')
            )
            ->first();

        $salesRevenue = $salesData->omset ?? 0;
        $salesProfit  = $salesRevenue - ($salesData->hpp ?? 0);

        // -------------------------------------------------------------
        // B. HITUNG OMSET & PROFIT (SERVICE - BARANG CONVERT)
        // -------------------------------------------------------------
        // Menggunakan STOCK_MOVEMENTS agar akurat dengan fisik (Net Retur)
        // Filter Tanggal menggunakan 'services.reg_date' (Tanggal Pengerjaan)
        
        $serviceData = DB::table('stock_movements')
            ->join('services', 'stock_movements.referensi_id', '=', 'services.id')
            ->join('barangs', 'stock_movements.barang_id', '=', 'barangs.id')
            ->where('stock_movements.referensi_type', 'like', '%Service%')
            ->whereIn('barangs.part_code', $validPartCodes)
            ->whereMonth('services.reg_date', now()->month) // Gunakan Reg Date (Sama dg Laporan)
            ->whereYear('services.reg_date', now()->year)
            ->select(
                // ABS(Sum Jumlah) karena barang keluar di stok itu negatif (-1). 
                // Kita absolutekan agar jadi positif untuk hitung omset.
                // Jika ada retur (+1), maka -1 + 1 = 0. Jadi netral. Akurat.
                DB::raw('ABS(SUM(stock_movements.jumlah * COALESCE(barangs.retail, 0))) as omset'),
                DB::raw('ABS(SUM(stock_movements.jumlah * COALESCE(barangs.selling_out, 0))) as hpp')
            )
            ->first();

        $serviceRevenue = $serviceData->omset ?? 0;
        $serviceProfit  = $serviceRevenue - ($serviceData->hpp ?? 0);

        // -------------------------------------------------------------
        // C. GRAND TOTAL
        // -------------------------------------------------------------
        $revenueThisMonth = $salesRevenue + $serviceRevenue;
        $profitThisMonth  = $salesProfit + $serviceProfit;

        // --- D. NILAI ASET STOK (GLOBAL) ---
        $inventoryAssetValue = DB::table('inventory_batches')
            ->join('barangs', 'inventory_batches.barang_id', '=', 'barangs.id')
            ->sum(DB::raw('inventory_batches.quantity * COALESCE(barangs.selling_out, 0)'));

        // --- E. TREN PENJUALAN (CHART) ---
        // Data Harian Penjualan
        $dailySales = Penjualan::select(DB::raw('DATE(tanggal_jual) as date'), DB::raw('SUM(total_harga) as total'))
            ->where('tanggal_jual', '>=', now()->subDays(30))
            ->groupBy('date')->pluck('total', 'date')->toArray();

        // Data Harian Service (Based on Stock Movement -> Reg Date)
        $dailyService = DB::table('stock_movements')
            ->join('services', 'stock_movements.referensi_id', '=', 'services.id')
            ->join('barangs', 'stock_movements.barang_id', '=', 'barangs.id')
            ->where('stock_movements.referensi_type', 'like', '%Service%')
            ->whereIn('barangs.part_code', $validPartCodes)
            ->where('services.reg_date', '>=', now()->subDays(30))
            ->select(
                DB::raw('DATE(services.reg_date) as date'),
                DB::raw('ABS(SUM(stock_movements.jumlah * COALESCE(barangs.retail, 0))) as total')
            )
            ->groupBy('date')->pluck('total', 'date')->toArray();

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

    // --- PIC / MANAGER (SINKRONISASI OMSET) ---
    private function getPicData()
    {
        $pendingPO = PurchaseOrder::where('status', 'PENDING_APPROVAL')->count();
        $pendingMutasi = StockMutation::where('status', 'PENDING_APPROVAL')->count();
        $pendingAdjustment = StockAdjustment::where('status', 'PENDING_APPROVAL')->count();
        $totalPending = $pendingPO + $pendingMutasi + $pendingAdjustment;

        $totalCabang = Lokasi::where('tipe', '!=', 'PUSAT')->count();
        $totalUser = User::where('is_active', true)->count();

        $criticalItems = DB::table('inventory_batches')
            ->join('barangs', 'inventory_batches.barang_id', '=', 'barangs.id')
            ->select('barangs.part_name', 'barangs.part_code', DB::raw('SUM(inventory_batches.quantity) as total_qty'), 'barangs.stok_minimum')
            ->groupBy('barangs.id', 'barangs.part_name', 'barangs.part_code', 'barangs.stok_minimum')
            ->havingRaw('SUM(inventory_batches.quantity) < barangs.stok_minimum')
            ->limit(5)->get();

        // TOP CABANG (Omset Sales + Service Net)
        $validPartCodes = DB::table('converts_main')->distinct()->pluck('part_code')->toArray();
        $targetMonth = now()->month;
        $targetYear = now()->year;

        // Sales
        $salesByLoc = DB::table('penjualans')
            ->whereMonth('tanggal_jual', $targetMonth)
            ->whereYear('tanggal_jual', $targetYear)
            ->select('lokasi_id', DB::raw('SUM(total_harga) as total'))
            ->groupBy('lokasi_id')->pluck('total', 'lokasi_id');

        // Service (Using Stock Movement Logic for Accuracy)
        $serviceByLoc = DB::table('stock_movements')
            ->join('services', 'stock_movements.referensi_id', '=', 'services.id')
            ->join('barangs', 'stock_movements.barang_id', '=', 'barangs.id')
            ->where('stock_movements.referensi_type', 'like', '%Service%')
            ->whereIn('barangs.part_code', $validPartCodes)
            ->whereMonth('services.reg_date', $targetMonth)
            ->whereYear('services.reg_date', $targetYear)
            ->select('services.lokasi_id', DB::raw('ABS(SUM(stock_movements.jumlah * COALESCE(barangs.retail, 0))) as total'))
            ->groupBy('services.lokasi_id')->pluck('total', 'lokasi_id');

        $allLocations = Lokasi::where('tipe', '!=', 'PUSAT')->get();
        $topCabang = $allLocations->map(function($lokasi) use ($salesByLoc, $serviceByLoc) {
            $salesTotal = $salesByLoc[$lokasi->id] ?? 0;
            $serviceTotal = $serviceByLoc[$lokasi->id] ?? 0;
            return (object) [
                'nama_lokasi' => $lokasi->nama_lokasi,
                'omset' => $salesTotal + $serviceTotal
            ];
        })->sortByDesc('omset')->take(5);

        return compact('pendingPO', 'pendingMutasi', 'pendingAdjustment', 'totalPending', 'totalCabang', 'totalUser', 'criticalItems', 'topCabang');
    }

    // --- OPERATOR ---
    private function getOperatorData($user)
    {
        $lokasiId = $user->lokasi_id;
        $isPusat = ($user->jabatan->singkatan === 'SA' || ($user->lokasi && $user->lokasi->tipe === 'PUSAT'));

        $taskCounts = [
            'receiving_po' => 0, 'qc' => 0, 'putaway' => 0, 
            'dealer_request_approval' => 0, 'incoming_mutation_transit' => 0
        ];
        $stockData = collect([]);
        $totalItemsSoldMonth = 0;

        if ($isPusat || !$lokasiId) { 
            $taskCounts['dealer_request_approval'] = PurchaseOrder::where('status', 'PENDING_APPROVAL')->where('po_type', 'dealer_request')->count();
        } else {
            $taskCounts['receiving_po'] = PurchaseOrder::where('lokasi_id', $lokasiId)->where('po_type', 'dealer_request')->whereIn('status', ['APPROVED', 'PARTIALLY_RECEIVED'])->count();
            $taskCounts['incoming_mutation_transit'] = StockMutation::where('lokasi_tujuan_id', $lokasiId)->where('status', 'IN_TRANSIT')->count();
            
            $stockData = DB::table('inventory_batches')
                ->join('barangs', 'inventory_batches.barang_id', '=', 'barangs.id')
                ->where('inventory_batches.lokasi_id', $lokasiId)
                ->select('barangs.part_name', 'barangs.part_code', 'barangs.stok_minimum', DB::raw('SUM(inventory_batches.quantity) as total_qty'))
                ->groupBy('barangs.id', 'barangs.part_name', 'barangs.part_code', 'barangs.stok_minimum')
                ->orderByRaw('(SUM(inventory_batches.quantity) < barangs.stok_minimum) DESC')
                ->limit(10)->get();

            // Total Fisik Terjual
            $validPartCodes = DB::table('converts_main')->distinct()->pluck('part_code')->toArray();
            $validBarangIds = Barang::whereIn('part_code', $validPartCodes)->pluck('id');

            $itemsFromSales = DB::table('penjualan_details')
                ->join('penjualans', 'penjualan_details.penjualan_id', '=', 'penjualans.id')
                ->where('penjualans.lokasi_id', $lokasiId)
                ->whereMonth('penjualans.tanggal_jual', now()->month)->sum('penjualan_details.qty_jual');

            $netServiceMovement = DB::table('stock_movements')
                ->where('referensi_type', 'like', '%Service%')
                ->where('lokasi_id', $lokasiId)
                ->whereIn('barang_id', $validBarangIds)
                ->whereMonth('created_at', now()->month)
                ->sum('jumlah');

            $totalItemsSoldMonth = $itemsFromSales + abs($netServiceMovement);
        }

        if($lokasiId) {
            $taskCounts['qc'] = Receiving::where('lokasi_id', $lokasiId)->where('status', 'PENDING_QC')->count();
            $taskCounts['putaway'] = Receiving::where('lokasi_id', $lokasiId)->where('status', 'PENDING_PUTAWAY')->count();
        }

        $lokasi = $lokasiId ? Lokasi::find($lokasiId) : (object)['nama_lokasi' => 'Global/Pusat'];

        return compact('taskCounts', 'lokasi', 'isPusat', 'stockData', 'totalItemsSoldMonth');
    }

    private function getKepalaCabangData($user)
    {
        $lokasiId = $user->lokasi_id;
        $lokasi = Lokasi::find($lokasiId);

        // 1. Statistik Harian
        $salesToday = Penjualan::where('lokasi_id', $lokasiId)->whereDate('tanggal_jual', today())->count();
        $serviceToday = DB::table('services')->where('lokasi_id', $lokasiId)->whereDate('created_at', today())->count();

        // 2. Approval Tasks
        // KC menyetujui Adjustment (Stok Opname) dari PC/Mekanik di cabangnya
        $pendingAdjustments = StockAdjustment::where('status', 'PENDING_APPROVAL')
            ->where('lokasi_id', $lokasiId)
            ->with('barang')
            ->latest()->take(5)->get();
        
        // KC menyetujui Mutasi Keluar (Jika ada permintaan dari cabang lain)
        $pendingMutations = StockMutation::where('status', 'PENDING_APPROVAL')
            ->where('lokasi_asal_id', $lokasiId) // Mutasi Keluar
            ->with('barang', 'lokasiTujuan')
            ->latest()->take(5)->get();

        return compact('lokasi', 'salesToday', 'serviceToday', 'pendingAdjustments', 'pendingMutations');
    }

    private function getAdminGudangData($user)
    {
        $lokasiId = $user->lokasi_id;

        // 1. Widget Counters
        $pendingApprovalPO = PurchaseOrder::where('status', 'PENDING_APPROVAL')
            ->where('created_by', $user->id) // PO yang dia request sendiri
            ->count();

        // PO yang masuk ke lokasi dia dan sudah approve (siap di-receive)
        $readyToReceivePO = PurchaseOrder::where('lokasi_id', $lokasiId)
            ->whereIn('status', ['APPROVED', 'PARTIALLY_RECEIVED'])
            ->count();

        $pendingQC = Receiving::where('lokasi_id', $lokasiId)
            ->where('status', 'PENDING_QC')
            ->count();

        // Menghitung yang siap putaway (Lolos QC atau Bypass QC)
        $pendingPutaway = Receiving::where('lokasi_id', $lokasiId)
            ->whereIn('status', ['QC_PASSED', 'PENDING_PUTAWAY'])
            ->count();

        // 2. Stok Kritis (Hanya di gudang ini)
        $stockAlerts = DB::table('inventory_batches')
            ->join('barangs', 'inventory_batches.barang_id', '=', 'barangs.id')
            ->where('inventory_batches.lokasi_id', $lokasiId)
            ->select(
                'barangs.part_name',
                'barangs.part_code',
                'barangs.stok_minimum',
                DB::raw('SUM(inventory_batches.quantity) as total_qty')
            )
            ->groupBy('barangs.id', 'barangs.part_name', 'barangs.part_code', 'barangs.stok_minimum')
            ->havingRaw('SUM(inventory_batches.quantity) <= barangs.stok_minimum')
            ->limit(5)
            ->get();

        // 3. Aktivitas Penerimaan Terakhir
        $recentReceivings = Receiving::with(['purchaseOrder.supplier', 'purchaseOrder.sumberLokasi'])
            ->where('lokasi_id', $lokasiId)
            ->latest()
            ->limit(5)
            ->get();

        return compact(
            'pendingApprovalPO',
            'readyToReceivePO',
            'pendingQC',
            'pendingPutaway',
            'stockAlerts',
            'recentReceivings'
        );
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
        $validBarangIds = Barang::whereIn('part_code', $validPartCodes)->pluck('id');

        $itemsFromSales = DB::table('penjualan_details')
            ->join('penjualans', 'penjualan_details.penjualan_id', '=', 'penjualans.id')
            ->where('penjualans.lokasi_id', $lokasiId)
            ->whereMonth('penjualans.tanggal_jual', now()->month)->sum('penjualan_details.qty_jual');

        $netServiceMovement = DB::table('stock_movements')
            ->where('referensi_type', 'like', '%Service%')
            ->where('lokasi_id', $lokasiId)
            ->whereIn('barang_id', $validBarangIds)
            ->whereMonth('created_at', now()->month)
            ->sum('jumlah');

        $totalItemsSoldMonth = $itemsFromSales + abs($netServiceMovement);

        return compact('serviceToday', 'serviceWeek', 'salesToday', 'salesWeek', 'totalItemsSoldMonth', 'lokasiId');
    }

    // --- LAINNYA ---
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

    private function getAsdData($user)
    {
        $lokasiId = $user->lokasi_id;
        
        // 1. DATA PENDING APPROVAL MUTASI (Tetap)
        $pendingMutations = StockMutation::with(['lokasiAsal', 'lokasiTujuan'])
            ->where('status', 'PENDING_APPROVAL')
            ->latest()
            ->limit(10)
            ->get();

        $countPendingMutations = StockMutation::where('status', 'PENDING_APPROVAL')->count();

        // 2. MASTER DATA OVERVIEW (Tetap)
        $totalItems = Barang::count();
        $totalConvertItems = 0;
        try {
             $totalConvertItems = DB::table('converts_main')->count(); 
        } catch (\Exception $e) {}

        // 3. TRANSAKSI HARI INI (Tetap)
        $servicesToday = DB::table('services')
            ->where('lokasi_id', $lokasiId)
            ->whereDate('created_at', today())
            ->count();
            
        $salesToday = Penjualan::where('lokasi_id', $lokasiId)
            ->whereDate('tanggal_jual', today())
            ->count();

        // 4. [UBAH DISINI] MONITORING STOK JARINGAN DEALER (Sama seperti Service MD)
        // Mengambil data stok dari seluruh lokasi tipe DEALER (bukan PUSAT)
        $stockData = DB::table('inventory_batches')
            ->join('barangs', 'inventory_batches.barang_id', '=', 'barangs.id')
            ->join('lokasi', 'inventory_batches.lokasi_id', '=', 'lokasi.id') 
            ->where('lokasi.tipe', '!=', 'PUSAT') // Filter hanya Dealer
            ->select(
                'lokasi.nama_lokasi', 
                'barangs.part_name',
                'barangs.part_code',
                'barangs.stok_minimum',
                DB::raw('SUM(inventory_batches.quantity) as total_qty')
            )
            ->groupBy(
                'lokasi.id', 'lokasi.nama_lokasi', 
                'barangs.id', 'barangs.part_name', 'barangs.part_code', 'barangs.stok_minimum'
            )
            // Urutkan: Yang KRITIS (kurang dari min) paling atas, lalu nama dealer
            ->orderByRaw('(SUM(inventory_batches.quantity) < barangs.stok_minimum) DESC')
            ->orderBy('lokasi.nama_lokasi')
            ->limit(20) // Batasi 20 record
            ->get();

        // 5. RECENT SERVICE ACTIVITY (Tetap)
        $recentServices = DB::table('services')
            ->where('lokasi_id', $lokasiId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return compact(
            'pendingMutations', 
            'countPendingMutations', 
            'totalItems', 
            'totalConvertItems',
            'servicesToday',
            'salesToday',
            'stockData', // Variable baru pengganti lowStockItems
            'recentServices'
        );
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
}