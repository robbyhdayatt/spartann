<?php

namespace App\Http\Controllers;

use App\Models\Lokasi;
use App\Models\Barang;
use App\Models\Part; 
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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $role = $user->jabatan->singkatan ?? 'KSR';

        $viewName = 'dashboards._default';
        $data = [];

        switch ($role) {
            case 'SA':
                $viewName = 'dashboards._superadmin';
                $data = $this->getSuperAdminData($request);
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
                $data = $this->getAccountingData($request);
                break;

            case 'PIC':
                $viewName = 'dashboards._pic';
                $data = $this->getPicData($request);
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

    public function searchYgp(Request $request)
    {
        $search = $request->q;
        $query = Part::where('is_active', 1);
        
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('kode_part', 'like', "%{$search}%")
                  ->orWhere('nama_part', 'like', "%{$search}%");
            });
        }
        
        $parts = $query->limit(50)->get();

        $results = [];
        foreach ($parts as $part) {
            $results[] = [
                'id' => $part->kode_part,
                'text' => $part->kode_part . ' - ' . $part->nama_part
            ];
        }

        return response()->json(['results' => $results]);
    }

    private function getSuperAdminData($request)
    {
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

        $finData = $this->getAccountingData($request);
        $picData = $this->getPicData($request);

        return array_merge($itData, $opsData, $finData, $picData);
    }

    // =========================================================================
    // FUNGSI ACCOUNTING (MEWARISI DATA PIC + METRIK KHUSUS AUDIT)
    // =========================================================================
    private function getAccountingData($request)
    {
        $data = $this->getPicData($request);

        // Valuasi Aset Fisik Gudang (Non-YGP) - Tetap dipertahankan di background karena SuperAdmin membutuhkannya
        $data['inventoryAssetValue'] = DB::table('inventory_batches')
            ->join('barangs', 'inventory_batches.barang_id', '=', 'barangs.id')
            ->sum(DB::raw('inventory_batches.quantity * COALESCE(barangs.selling_out, 0)'));

        // Metrik Pengganti Valuasi Aset: Total Transaksi Faktur POS (Sesuai Filter)
        $data['totalFaktur'] = DB::table('penjualans')
            ->whereBetween('tanggal_jual', [$data['filter']['startDate'], $data['filter']['endDate']])
            ->when($data['filter']['filterLokasi'] !== 'all', function($q) use ($data) {
                return $q->where('lokasi_id', $data['filter']['filterLokasi']);
            })
            ->count();

        // Riwayat Transaksi Faktur Terakhir
        $data['recentTransactions'] = Penjualan::with(['lokasi', 'sales'])
            ->latest('created_at')
            ->limit(10)
            ->get();

        return $data;
    }

    private function getOperatorData($user)
    {
        $lokasiId = $user->lokasi_id;
        $isPusat = ($user->jabatan->singkatan === 'SA' || ($user->lokasi && $user->lokasi->tipe === 'PUSAT'));

        $taskCounts = ['receiving_po' => 0, 'qc' => 0, 'putaway' => 0, 'dealer_request_approval' => 0, 'incoming_mutation_transit' => 0];
        $stockData = collect([]);
        $totalItemsSoldMonth = 0;

        $currentYear = Carbon::now()->year;
        $currentMonth = Carbon::now()->month;
        $startOfWeek = Carbon::now()->startOfWeek()->format('Y-m-d');
        $endOfWeek = Carbon::now()->endOfWeek()->format('Y-m-d');

        $targetBarangIds = [1, 2, 3];

        $omset = [
            'penjualan' => ['minggu' => ['omset' => 0, 'profit' => 0], 'bulan'  => ['omset' => 0, 'profit' => 0], 'tahun'  => ['omset' => 0, 'profit' => 0]],
            'service' => ['minggu' => ['omset' => 0, 'profit' => 0], 'bulan'  => ['omset' => 0, 'profit' => 0], 'tahun'  => ['omset' => 0, 'profit' => 0]]
        ];

        $qty = ['penjualan' => ['minggu' => 0, 'bulan' => 0, 'tahun' => 0], 'service'   => ['minggu' => 0, 'bulan' => 0, 'tahun' => 0]];

        $chart = [
            'labels' => [
                'minggu' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'],
                'tahun'  => ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'],
                'bulan'  => range(1, Carbon::now()->daysInMonth)
            ],
            'penjualan' => [
                'minggu' => ['omset' => array_fill(0, 7, 0), 'profit' => array_fill(0, 7, 0)],
                'bulan'  => ['omset' => array_fill(0, Carbon::now()->daysInMonth, 0), 'profit' => array_fill(0, Carbon::now()->daysInMonth, 0)],
                'tahun'  => ['omset' => array_fill(0, 12, 0), 'profit' => array_fill(0, 12, 0)]
            ],
            'service' => [
                'minggu' => ['omset' => array_fill(0, 7, 0), 'profit' => array_fill(0, 7, 0)],
                'bulan'  => ['omset' => array_fill(0, Carbon::now()->daysInMonth, 0), 'profit' => array_fill(0, Carbon::now()->daysInMonth, 0)],
                'tahun'  => ['omset' => array_fill(0, 12, 0), 'profit' => array_fill(0, 12, 0)]
            ]
        ];

        if ($isPusat || !$lokasiId) { 
            $taskCounts['dealer_request_approval'] = PurchaseOrder::where('status', 'PENDING_APPROVAL')->where('po_type', 'dealer_request')->count();
        } else {
            $taskCounts['receiving_po'] = PurchaseOrder::where('lokasi_id', $lokasiId)->where('po_type', 'dealer_request')->whereIn('status', ['APPROVED', 'PARTIALLY_RECEIVED'])->count();
            $taskCounts['incoming_mutation_transit'] = StockMutation::where('lokasi_tujuan_id', $lokasiId)->where('status', 'IN_TRANSIT')->count();
            
            $stockData = DB::table('inventory_batches')
                ->join('barangs', 'inventory_batches.barang_id', '=', 'barangs.id')
                ->where('inventory_batches.lokasi_id', $lokasiId)
                ->whereIn('inventory_batches.barang_id', $targetBarangIds)
                ->select('barangs.part_name', 'barangs.part_code', 'barangs.stok_minimum', DB::raw('SUM(inventory_batches.quantity) as total_qty'))
                ->groupBy('barangs.id', 'barangs.part_name', 'barangs.part_code', 'barangs.stok_minimum')
                ->get();

            $itemsFromSales = DB::table('penjualan_details')
                ->join('penjualans', 'penjualan_details.penjualan_id', '=', 'penjualans.id')
                ->where('penjualans.lokasi_id', $lokasiId)
                ->whereIn('penjualan_details.barang_id', $targetBarangIds)
                ->whereMonth('penjualans.tanggal_jual', now()->month)->sum('penjualan_details.qty_jual');

            $netServiceMovement = DB::table('stock_movements')
                ->where('referensi_type', 'like', '%Service%')
                ->where('lokasi_id', $lokasiId)
                ->whereIn('barang_id', $targetBarangIds)
                ->whereMonth('created_at', now()->month)
                ->sum('jumlah');

            $totalItemsSoldMonth = $itemsFromSales + abs($netServiceMovement);

            $posData = DB::table('penjualan_details')
                ->join('penjualans', 'penjualan_details.penjualan_id', '=', 'penjualans.id')
                ->where('penjualans.lokasi_id', $lokasiId)
                ->whereIn('penjualan_details.barang_id', $targetBarangIds)
                ->whereYear('penjualans.tanggal_jual', $currentYear)
                ->get(['penjualans.tanggal_jual', 'penjualan_details.subtotal', 'penjualan_details.qty_jual', 'penjualan_details.harga_modal']);

            foreach ($posData as $p) {
                $date = Carbon::parse($p->tanggal_jual);
                $valOmset = (float) $p->subtotal;
                $valProfit = $valOmset - ($p->qty_jual * $p->harga_modal); 
                $valQty = (int) $p->qty_jual;

                $omset['penjualan']['tahun']['omset'] += $valOmset; $omset['penjualan']['tahun']['profit'] += $valProfit; $qty['penjualan']['tahun'] += $valQty;
                $chart['penjualan']['tahun']['omset'][$date->month - 1] += $valOmset; $chart['penjualan']['tahun']['profit'][$date->month - 1] += $valProfit;

                if ($date->month == $currentMonth) {
                    $omset['penjualan']['bulan']['omset'] += $valOmset; $omset['penjualan']['bulan']['profit'] += $valProfit; $qty['penjualan']['bulan'] += $valQty;
                    $chart['penjualan']['bulan']['omset'][$date->day - 1] += $valOmset; $chart['penjualan']['bulan']['profit'][$date->day - 1] += $valProfit;
                }

                if ($date->format('Y-m-d') >= $startOfWeek && $date->format('Y-m-d') <= $endOfWeek) {
                    $omset['penjualan']['minggu']['omset'] += $valOmset; $omset['penjualan']['minggu']['profit'] += $valProfit; $qty['penjualan']['minggu'] += $valQty;
                    $chart['penjualan']['minggu']['omset'][$date->dayOfWeekIso - 1] += $valOmset; $chart['penjualan']['minggu']['profit'][$date->dayOfWeekIso - 1] += $valProfit;
                }
            }

            $serviceData = DB::table('service_details')
                ->join('services', 'service_details.service_id', '=', 'services.id')
                ->where('services.lokasi_id', $lokasiId)
                ->whereIn('service_details.barang_id', $targetBarangIds)
                ->whereYear('services.reg_date', $currentYear)
                ->get(['services.reg_date', 'service_details.quantity', 'service_details.price', 'service_details.cost_price']);

            foreach ($serviceData as $s) {
                $date = Carbon::parse($s->reg_date);
                $valOmset = (float) ($s->quantity * $s->price); 
                $valHPP = (float) ($s->quantity * $s->cost_price);
                $valProfit = $valOmset - $valHPP;
                $valQty = (int) $s->quantity;

                $omset['service']['tahun']['omset'] += $valOmset; $omset['service']['tahun']['profit'] += $valProfit; $qty['service']['tahun'] += $valQty;
                $chart['service']['tahun']['omset'][$date->month - 1] += $valOmset; $chart['service']['tahun']['profit'][$date->month - 1] += $valProfit;

                if ($date->month == $currentMonth) {
                    $omset['service']['bulan']['omset'] += $valOmset; $omset['service']['bulan']['profit'] += $valProfit; $qty['service']['bulan'] += $valQty;
                    $chart['service']['bulan']['omset'][$date->day - 1] += $valOmset; $chart['service']['bulan']['profit'][$date->day - 1] += $valProfit;
                }

                if ($date->format('Y-m-d') >= $startOfWeek && $date->format('Y-m-d') <= $endOfWeek) {
                    $omset['service']['minggu']['omset'] += $valOmset; $omset['service']['minggu']['profit'] += $valProfit; $qty['service']['minggu'] += $valQty;
                    $chart['service']['minggu']['omset'][$date->dayOfWeekIso - 1] += $valOmset; $chart['service']['minggu']['profit'][$date->dayOfWeekIso - 1] += $valProfit;
                }
            }
        }

        if($lokasiId) {
            $taskCounts['qc'] = Receiving::where('lokasi_id', $lokasiId)->where('status', 'PENDING_QC')->count();
            $taskCounts['putaway'] = Receiving::where('lokasi_id', $lokasiId)->where('status', 'PENDING_PUTAWAY')->count();
        }

        $lokasi = $lokasiId ? Lokasi::find($lokasiId) : (object)['nama_lokasi' => 'Global/Pusat'];

        return compact('taskCounts', 'lokasi', 'isPusat', 'stockData', 'totalItemsSoldMonth', 'omset', 'qty', 'chart');
    }

    private function getPicData($request)
    {
        $startDate = $request->start_date ?? Carbon::now()->startOfMonth()->format('Y-m-d');
        $endDate = $request->end_date ?? Carbon::now()->endOfMonth()->format('Y-m-d');
        $filterLokasi = $request->lokasi_id ?? 'all';
        $filterNonYgp = $request->barang_id ?? 'all';
        $filterYgp = $request->part_code ?? 'all';

        $startDateObj = Carbon::parse($startDate);
        $endDateObj = Carbon::parse($endDate);

        $prevStartDate = $startDateObj->copy()->subMonthNoOverflow()->format('Y-m-d');
        if ($endDateObj->isSameDay($endDateObj->copy()->endOfMonth())) {
            $prevEndDate = $endDateObj->copy()->subMonthNoOverflow()->endOfMonth()->format('Y-m-d');
        } else {
            $prevEndDate = $endDateObj->copy()->subMonthNoOverflow()->format('Y-m-d');
        }

        $selectedYgpName = 'Semua YGP';
        if ($filterYgp !== 'all') {
            $ygpData = Part::where('kode_part', $filterYgp)->first();
            if ($ygpData) {
                $selectedYgpName = $ygpData->kode_part . ' - ' . $ygpData->nama_part;
            }
        }

        $pendingPO = PurchaseOrder::where('status', 'PENDING_APPROVAL')->count();
        $pendingMutasi = StockMutation::where('status', 'PENDING_APPROVAL')->count();
        $pendingAdjustment = StockAdjustment::where('status', 'PENDING_APPROVAL')->count();
        $totalPending = $pendingPO + $pendingMutasi + $pendingAdjustment;

        $daftarLokasi = Lokasi::where('tipe', 'DEALER')->get();
        $daftarBarang = DB::table('barangs')->get(); 

        $retailNonYgpQuery = DB::table('penjualan_details')
            ->join('penjualans', 'penjualan_details.penjualan_id', '=', 'penjualans.id')
            ->select(
                'penjualans.lokasi_id', 'penjualans.tanggal_jual as tgl', 
                'penjualan_details.subtotal as omset', 
                DB::raw('(penjualan_details.qty_jual * penjualan_details.harga_modal) as hpp'),
                'penjualan_details.qty_jual as qty', 'penjualan_details.barang_id', DB::raw('NULL as item_code')
            )
            ->whereNotNull('penjualan_details.barang_id')->whereBetween('penjualans.tanggal_jual', [$startDate, $endDate])
            ->when($filterLokasi !== 'all', function($q) use ($filterLokasi) { return $q->where('penjualans.lokasi_id', $filterLokasi); })
            ->when($filterNonYgp !== 'all', function($q) use ($filterNonYgp) { return $q->where('penjualan_details.barang_id', $filterNonYgp); });

        $retailYgpQuery = DB::table('service_details')
            ->join('services', 'service_details.service_id', '=', 'services.id')
            ->select(
                'services.lokasi_id', 'services.reg_date as tgl', 
                'service_details.price as omset', 
                DB::raw('(service_details.quantity * service_details.cost_price) as hpp'),
                'service_details.quantity as qty', 'service_details.barang_id', 'service_details.item_code'
            )
            ->where('services.service_order', 'Part Retail')->whereBetween('services.reg_date', [$startDate, $endDate])
            ->when($filterLokasi !== 'all', function($q) use ($filterLokasi) { return $q->where('services.lokasi_id', $filterLokasi); })
            ->when($filterYgp !== 'all', function($q) use ($filterYgp) { return $q->where('service_details.item_code', $filterYgp); });

        $serviceNonYgpQuery = DB::table('service_details')
            ->join('services', 'service_details.service_id', '=', 'services.id')
            ->select(
                'services.lokasi_id', 'services.reg_date as tgl', 
                'service_details.price as omset', DB::raw('(service_details.quantity * service_details.cost_price) as hpp'),
                'service_details.quantity as qty', 'service_details.barang_id', DB::raw('NULL as item_code')
            )
            ->where('services.service_order', 'LIKE', '%service%')->whereNotNull('service_details.barang_id')
            ->whereBetween('services.reg_date', [$startDate, $endDate])
            ->when($filterLokasi !== 'all', function($q) use ($filterLokasi) { return $q->where('services.lokasi_id', $filterLokasi); })
            ->when($filterNonYgp !== 'all', function($q) use ($filterNonYgp) { return $q->where('service_details.barang_id', $filterNonYgp); });

        $serviceYgpQuery = DB::table('service_details')
            ->join('services', 'service_details.service_id', '=', 'services.id')
            ->join('parts', 'service_details.item_code', '=', 'parts.kode_part') 
            ->select(
                'services.lokasi_id', 'services.reg_date as tgl', 
                'service_details.price as omset', DB::raw('(service_details.quantity * service_details.cost_price) as hpp'),
                'service_details.quantity as qty', 'service_details.barang_id', 'service_details.item_code'
            )
            ->where('services.service_order', 'LIKE', '%service%')->whereBetween('services.reg_date', [$startDate, $endDate])
            ->when($filterLokasi !== 'all', function($q) use ($filterLokasi) { return $q->where('services.lokasi_id', $filterLokasi); })
            ->when($filterYgp !== 'all', function($q) use ($filterYgp) { return $q->where('service_details.item_code', $filterYgp); });

        $dataRetail = collect($retailNonYgpQuery->unionAll($retailYgpQuery)->get());
        $dataService = collect($serviceNonYgpQuery->unionAll($serviceYgpQuery)->get());
        
        $prevRetailNonYgpQuery = DB::table('penjualan_details')->join('penjualans', 'penjualan_details.penjualan_id', '=', 'penjualans.id')
            ->select('penjualan_details.qty_jual as qty', 'penjualan_details.subtotal as omset')
            ->whereNotNull('penjualan_details.barang_id')->whereBetween('penjualans.tanggal_jual', [$prevStartDate, $prevEndDate])
            ->when($filterLokasi !== 'all', function($q) use ($filterLokasi) { return $q->where('penjualans.lokasi_id', $filterLokasi); })
            ->when($filterNonYgp !== 'all', function($q) use ($filterNonYgp) { return $q->where('penjualan_details.barang_id', $filterNonYgp); });

        $prevRetailYgpQuery = DB::table('service_details')->join('services', 'service_details.service_id', '=', 'services.id')
            ->select('service_details.quantity as qty', 'service_details.price as omset')
            ->where('services.service_order', 'Part Retail')->whereBetween('services.reg_date', [$prevStartDate, $prevEndDate])
            ->when($filterLokasi !== 'all', function($q) use ($filterLokasi) { return $q->where('services.lokasi_id', $filterLokasi); })
            ->when($filterYgp !== 'all', function($q) use ($filterYgp) { return $q->where('service_details.item_code', $filterYgp); });

        $prevServiceNonYgpQuery = DB::table('service_details')->join('services', 'service_details.service_id', '=', 'services.id')
            ->select('service_details.quantity as qty', 'service_details.price as omset')
            ->where('services.service_order', 'LIKE', '%service%')->whereNotNull('service_details.barang_id')
            ->whereBetween('services.reg_date', [$prevStartDate, $prevEndDate])
            ->when($filterLokasi !== 'all', function($q) use ($filterLokasi) { return $q->where('services.lokasi_id', $filterLokasi); })
            ->when($filterNonYgp !== 'all', function($q) use ($filterNonYgp) { return $q->where('service_details.barang_id', $filterNonYgp); });

        $prevServiceYgpQuery = DB::table('service_details')->join('services', 'service_details.service_id', '=', 'services.id')
            ->join('parts', 'service_details.item_code', '=', 'parts.kode_part')
            ->select('service_details.quantity as qty', 'service_details.price as omset')
            ->where('services.service_order', 'LIKE', '%service%')->whereBetween('services.reg_date', [$prevStartDate, $prevEndDate])
            ->when($filterLokasi !== 'all', function($q) use ($filterLokasi) { return $q->where('services.lokasi_id', $filterLokasi); })
            ->when($filterYgp !== 'all', function($q) use ($filterYgp) { return $q->where('service_details.item_code', $filterYgp); });

        $prevDataRetail = collect($prevRetailNonYgpQuery->unionAll($prevRetailYgpQuery)->get());
        $prevDataService = collect($prevServiceNonYgpQuery->unionAll($prevServiceYgpQuery)->get());

        $totalRetailOmset = $dataRetail->sum('omset');
        $totalRetailHpp = $dataRetail->sum('hpp');
        $totalRetailQty = $dataRetail->sum('qty');
        
        $totalServiceOmset = $dataService->sum('omset');
        $totalServiceHpp = $dataService->sum('hpp');
        $totalServiceQty = $dataService->sum('qty');

        $totalPrevRetailQty = $prevDataRetail->sum('qty');
        $totalPrevServiceQty = $prevDataService->sum('qty');
        $totalPrevRetailOmset = $prevDataRetail->sum('omset');
        $totalPrevServiceOmset = $prevDataService->sum('omset');
        
        $grandTotalOmset = $totalRetailOmset + $totalServiceOmset;
        $grandTotalLaba = ($totalRetailOmset - $totalRetailHpp) + ($totalServiceOmset - $totalServiceHpp);
        $grandTotalQty = $totalRetailQty + $totalServiceQty;
        $grossProfitMargin = $grandTotalOmset > 0 ? round(($grandTotalLaba / $grandTotalOmset) * 100, 2) : 0;

        $omsetPie = ['retail' => $totalRetailOmset, 'service' => $totalServiceOmset];
        $qtyPie = ['retail' => $totalRetailQty, 'service' => $totalServiceQty];

        $chartLabels = [];
        $chartRetailOmset = []; $chartServiceOmset = [];
        $chartRetailQty = []; $chartServiceQty = [];
        
        $retailGrouped = $dataRetail->groupBy('tgl');
        $serviceGrouped = $dataService->groupBy('tgl');

        $period = \Carbon\CarbonPeriod::create($startDate, $endDate);
        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $chartLabels[] = $date->format('d M');
            
            $chartRetailOmset[] = isset($retailGrouped[$dateStr]) ? $retailGrouped[$dateStr]->sum('omset') : 0;
            $chartRetailQty[] = isset($retailGrouped[$dateStr]) ? $retailGrouped[$dateStr]->sum('qty') : 0;
            
            $chartServiceOmset[] = isset($serviceGrouped[$dateStr]) ? $serviceGrouped[$dateStr]->sum('omset') : 0;
            $chartServiceQty[] = isset($serviceGrouped[$dateStr]) ? $serviceGrouped[$dateStr]->sum('qty') : 0;
        }

        $allTransactions = collect($dataRetail)->merge($dataService);
        
        $itemStats = [];
        $cabangStats = [];

        foreach($allTransactions as $trx) {
            $key = $trx->barang_id ? 'NON_YGP_'.$trx->barang_id : 'YGP_'.$trx->item_code;
            if(!isset($itemStats[$key])) {
                $itemStats[$key] = ['omset' => 0, 'qty' => 0, 'barang_id' => $trx->barang_id, 'item_code' => $trx->item_code];
            }
            $itemStats[$key]['omset'] += $trx->omset;
            $itemStats[$key]['qty'] += $trx->qty;

            if(!isset($cabangStats[$trx->lokasi_id])) {
                $cabangStats[$trx->lokasi_id] = ['omset' => 0, 'qty' => 0];
            }
            $cabangStats[$trx->lokasi_id]['omset'] += $trx->omset;
            $cabangStats[$trx->lokasi_id]['qty'] += $trx->qty;
        }

        $mappedItems = collect($itemStats)->map(function($item) {
            if ($item['barang_id']) {
                $b = Barang::find($item['barang_id']);
                $item['name'] = $b ? $b->part_name . ' (Non-YGP)' : 'Unknown';
            } else {
                $p = Part::where('kode_part', $item['item_code'])->first();
                $item['name'] = $p ? $p->nama_part . ' (YGP)' : 'Unknown';
            }
            return (object) $item;
        });

        $topItemsOmset = $mappedItems->sortByDesc('omset')->take(5)->values();
        $topItemsQty = $mappedItems->sortByDesc('qty')->take(5)->values();

        $mappedCabang = collect($cabangStats)->map(function($stats, $lokasi_id) {
            $loc = Lokasi::find($lokasi_id);
            return (object) [
                'nama_lokasi' => $loc ? $loc->nama_lokasi : 'Unknown', 
                'omset' => $stats['omset'], 
                'qty' => $stats['qty']
            ];
        });

        $topCabangOmset = $mappedCabang->sortByDesc('omset')->take(5)->values();
        $topCabangQty = $mappedCabang->sortByDesc('qty')->take(5)->values();

        $filter = compact('startDate', 'endDate', 'filterLokasi', 'filterNonYgp', 'filterYgp', 'prevStartDate', 'prevEndDate');

        return compact(
            'pendingPO', 'pendingMutasi', 'pendingAdjustment', 'totalPending', 
            'daftarLokasi', 'daftarBarang', 'filter', 'selectedYgpName',
            'totalRetailOmset', 'totalServiceOmset', 'grandTotalOmset', 'grandTotalLaba', 'grossProfitMargin',
            'totalPrevRetailOmset', 'totalPrevServiceOmset',
            'omsetPie', 'chartRetailOmset', 'chartServiceOmset', 'topItemsOmset', 'topCabangOmset',
            'totalRetailQty', 'totalServiceQty', 'grandTotalQty', 'totalPrevRetailQty', 'totalPrevServiceQty',
            'qtyPie', 'chartRetailQty', 'chartServiceQty', 'topItemsQty', 'topCabangQty',
            'chartLabels'
        );
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
            ->where('po_type', 'dealer_request')
            ->where('sumber_lokasi_id', $lokasiId) // Yang sumber pengirimannya adalah Gudang ini
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
        $myRequests = PurchaseOrder::where('created_by', $user->id)
            ->where('po_type', 'dealer_request')
            ->latest()
            ->take(5)
            ->get();
            
        $stockData = DB::table('inventory_batches')
            ->join('barangs', 'inventory_batches.barang_id', '=', 'barangs.id')
            ->join('lokasi', 'inventory_batches.lokasi_id', '=', 'lokasi.id')
            // [PERBAIKAN] Ganti '!=', 'PUSAT' menjadi '=', 'DEALER'
            ->where('lokasi.tipe', '=', 'DEALER') 
            ->select('lokasi.nama_lokasi', 'barangs.part_name', 'barangs.part_code', 'barangs.stok_minimum', DB::raw('SUM(inventory_batches.quantity) as total_qty'))
            ->groupBy('lokasi.id', 'lokasi.nama_lokasi', 'barangs.id', 'barangs.part_name', 'barangs.part_code', 'barangs.stok_minimum')
            ->orderByRaw('(SUM(inventory_batches.quantity) < barangs.stok_minimum) DESC')
            ->orderBy('lokasi.nama_lokasi')
            ->limit(20)
            ->get();
            
        $totalStokCount = DB::table('inventory_batches')
            ->join('lokasi', 'inventory_batches.lokasi_id', '=', 'lokasi.id')
            // [PERBAIKAN] Ganti juga di perhitungan total ini
            ->where('lokasi.tipe', '=', 'DEALER') 
            ->sum('quantity');

        return [
            'targetAmount' => 0, 
            'achievedAmount' => 0, 
            'achievementPercentage' => 0, 
            'jumlahInsentif' => 0, 
            'recentSales' => collect([]), 
            'myRequests' => $myRequests, 
            'totalStokCount' => $totalStokCount, 
            'stockData' => $stockData, 
            'isSMD' => true
        ];
    }

    private function getAsdData($user)
    {
        // 1. KASIR WIDGET DATA (Global Area / Seluruh Cabang untuk ASD)
        $serviceToday = DB::table('services')->whereDate('created_at', today())->count();
        $serviceWeek = DB::table('services')->whereBetween('created_at', [now()->subDays(7), now()])->count();
        $salesToday = DB::table('penjualans')->whereDate('tanggal_jual', today())->count();
        $salesWeek = DB::table('penjualans')->whereBetween('tanggal_jual', [now()->subDays(7), now()])->count();

        // 2. ITEM TERJUAL BULAN INI (Part & Oli dari Service + Penjualan Langsung)
        $validPartCodes = DB::table('converts_main')->distinct()->pluck('part_code')->toArray();
        $validBarangIds = \App\Models\Barang::whereIn('part_code', $validPartCodes)->pluck('id');

        $itemsFromSales = DB::table('penjualan_details')
            ->join('penjualans', 'penjualan_details.penjualan_id', '=', 'penjualans.id')
            ->whereMonth('penjualans.tanggal_jual', now()->month)
            ->sum('penjualan_details.qty_jual');

        $netServiceMovement = DB::table('stock_movements')
            ->where('referensi_type', 'like', '%Service%')
            ->whereIn('barang_id', $validBarangIds)
            ->whereMonth('created_at', now()->month)
            ->sum('jumlah');

        $totalItemsSoldMonth = $itemsFromSales + abs($netServiceMovement);

        // 3. MONITORING STOK JARINGAN DEALER (Sama seperti perbaikan kita sebelumnya)
        $stockData = DB::table('inventory_batches')
            ->join('barangs', 'inventory_batches.barang_id', '=', 'barangs.id')
            ->join('lokasi', 'inventory_batches.lokasi_id', '=', 'lokasi.id') 
            ->where('lokasi.tipe', '=', 'DEALER') // Hanya Dealer
            ->select('lokasi.nama_lokasi', 'barangs.part_name', 'barangs.part_code', 'barangs.stok_minimum', DB::raw('SUM(inventory_batches.quantity) as total_qty'))
            ->groupBy('lokasi.id', 'lokasi.nama_lokasi', 'barangs.id', 'barangs.part_name', 'barangs.part_code', 'barangs.stok_minimum')
            ->orderByRaw('(SUM(inventory_batches.quantity) < barangs.stok_minimum) DESC')
            ->orderBy('lokasi.nama_lokasi')
            ->limit(20)
            ->get();

        // 4. CHART DATA: TREN 30 HARI TERAKHIR (Service vs Penjualan)
        $dailySales = DB::table('penjualans')
            ->where('tanggal_jual', '>=', now()->subDays(30))
            ->select(DB::raw('DATE(tanggal_jual) as date'), DB::raw('COUNT(*) as total'))
            ->groupBy('date')->pluck('total', 'date')->toArray();

        $dailyService = DB::table('services')
            ->where('created_at', '>=', now()->subDays(30))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as total'))
            ->groupBy('date')->pluck('total', 'date')->toArray();

        $chartLabels = [];
        $salesChartData = [];
        $serviceChartData = [];
        
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $chartLabels[] = now()->subDays($i)->format('d M');
            $salesChartData[] = $dailySales[$date] ?? 0;
            $serviceChartData[] = $dailyService[$date] ?? 0;
        }

        return compact(
            'serviceToday', 'serviceWeek', 'salesToday', 'salesWeek', 'totalItemsSoldMonth',
            'stockData', 'chartLabels', 'salesChartData', 'serviceChartData'
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