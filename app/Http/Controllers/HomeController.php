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
use Illuminate\Support\Facades\Cache;

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
        $cacheKey = 'search_ygp_' . md5($search);

        $results = Cache::remember($cacheKey, now()->addMinutes(5), function() use ($search) {
            $query = Part::where('is_active', 1);
            
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('kode_part', 'like', "%{$search}%")
                      ->orWhere('nama_part', 'like', "%{$search}%");
                });
            }
            
            $parts = $query->limit(50)->get();
            $results = [];

            if (!$search || stripos('semua', $search) !== false || stripos('all', $search) !== false) {
                $results[] = [
                    'id' => 'all', 
                    'text' => '-- Semua YGP --'
                ];
            }
            
            if (!$search || stripos('sembunyi', $search) !== false || stripos('none', $search) !== false) {
                $results[] = [
                    'id' => 'none', 
                    'text' => '-- Sembunyikan Semua YGP --'
                ];
            }

            foreach ($parts as $part) {
                $results[] = [
                    'id' => $part->kode_part,
                    'text' => $part->kode_part . ' - ' . $part->nama_part
                ];
            }

            return $results;
        });

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
            // [MODIFIKASI]: Menarik data dealer yang sudah melakukan import (Relasi dealer_code & kode_lokasi)
            'activeDealersImport' => Lokasi::whereIn('kode_lokasi', function($q) {
                $q->select('dealer_code')->from('services')->distinct();
            })->get(['kode_lokasi', 'nama_lokasi']),
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

    private function getAccountingData($request)
    {
        $data = $this->getPicData($request);

        $data['inventoryAssetValue'] = DB::table('inventory_batches')
            ->join('barangs', 'inventory_batches.barang_id', '=', 'barangs.id')
            ->sum(DB::raw('inventory_batches.quantity * COALESCE(barangs.selling_out, 0)'));

        $filterL = $data['filter']['filterLokasi'];
        $startD  = $data['filter']['startDate'];
        $endD    = $data['filter']['endDate'];

        $countPenjualan = DB::table('penjualans')
            ->whereBetween('tanggal_jual', [$startD, $endD])
            ->when($filterL !== 'all', function($q) use ($filterL) {
                return $q->where('lokasi_id', $filterL);
            })->count();

        // [MODIFIKASI SOP] Ganti DATE(reg_date) ke DATE(created_at)
        $countService = DB::table('services')
            ->whereBetween(DB::raw('DATE(created_at)'), [$startD, $endD])
            ->when($filterL !== 'all', function($q) use ($filterL) {
                return $q->where('lokasi_id', $filterL);
            })->count();

        $data['totalFaktur'] = $countPenjualan + $countService;

        $data['recentTransactions'] = Penjualan::with(['lokasi', 'sales'])
            ->latest('created_at')
            ->limit(10)
            ->get();

        $data['stockData'] = DB::table('inventory_batches')
            ->join('barangs', 'inventory_batches.barang_id', '=', 'barangs.id')
            ->join('lokasi', 'inventory_batches.lokasi_id', '=', 'lokasi.id') 
            ->where('lokasi.tipe', '=', 'DEALER')
            ->select('lokasi.nama_lokasi', 'barangs.part_name', 'barangs.part_code', 'barangs.stok_minimum', DB::raw('SUM(inventory_batches.quantity) as total_qty'))
            ->groupBy('lokasi.id', 'lokasi.nama_lokasi', 'barangs.id', 'barangs.part_name', 'barangs.part_code', 'barangs.stok_minimum')
            ->orderByRaw('(SUM(inventory_batches.quantity) < barangs.stok_minimum) DESC')
            ->orderBy('lokasi.nama_lokasi')
            ->limit(20)
            ->get();

        return $data;
    }

    private function getOperatorData($user)
    {
        $request = request();
        $lokasiId = $user->lokasi_id;
        
        $request->merge(['lokasi_id' => $lokasiId]);
        $data = $this->getPicData($request);
        
        $data['isPusat'] = false;
        $data['lokasi'] = Lokasi::find($lokasiId);
        
        $data['pendingReceive'] = PurchaseOrder::where('lokasi_id', $lokasiId)
            ->where('po_type', 'dealer_request')
            ->whereIn('status', ['APPROVED', 'PARTIALLY_RECEIVED'])
            ->count();
            
        $data['pendingPutaway'] = Receiving::where('lokasi_id', $lokasiId)
            ->where('status', 'PENDING_PUTAWAY')
            ->count();
            
        $data['stockData'] = DB::table('inventory_batches')
            ->join('barangs', 'inventory_batches.barang_id', '=', 'barangs.id')
            ->where('inventory_batches.lokasi_id', $lokasiId)
            ->select('barangs.part_name', 'barangs.part_code', 'barangs.stok_minimum', DB::raw('SUM(inventory_batches.quantity) as total_qty'))
            ->groupBy('barangs.id', 'barangs.part_name', 'barangs.part_code', 'barangs.stok_minimum')
            ->orderByRaw('(SUM(inventory_batches.quantity) < barangs.stok_minimum) DESC')
            ->limit(20)
            ->get();
            
        return $data;
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
        if ($filterYgp !== 'all' && $filterYgp !== 'none') {
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
            ->when($filterNonYgp !== 'all', function($q) use ($filterNonYgp) { 
                if ($filterNonYgp === 'none') return $q->whereRaw('1 = 0');
                return $q->where('penjualan_details.barang_id', $filterNonYgp); 
            });

        // [MODIFIKASI SOP] Ganti filter whereBetween dan kolom select dari reg_date menjadi DATE(created_at)
        $retailYgpQuery = DB::table('service_details')
            ->join('services', 'service_details.service_id', '=', 'services.id')
            ->select(
                'services.lokasi_id', DB::raw('DATE(services.created_at) as tgl'), 
                DB::raw('(service_details.quantity * service_details.price) as omset'), 
                DB::raw('(service_details.quantity * service_details.cost_price) as hpp'),
                'service_details.quantity as qty', 'service_details.barang_id', 'service_details.item_code'
            )
            ->where('services.service_order', 'Part Retail')
            ->whereBetween(DB::raw('DATE(services.created_at)'), [$startDate, $endDate])
            ->when($filterLokasi !== 'all', function($q) use ($filterLokasi) { return $q->where('services.lokasi_id', $filterLokasi); })
            ->when($filterYgp !== 'all', function($q) use ($filterYgp) { 
                if ($filterYgp === 'none') return $q->whereRaw('1 = 0');
                return $q->where('service_details.item_code', $filterYgp); 
            });

        // [MODIFIKASI SOP] Ganti filter whereBetween dan kolom select dari reg_date menjadi DATE(created_at)
        $serviceNonYgpQuery = DB::table('service_details')
            ->join('services', 'service_details.service_id', '=', 'services.id')
            ->select(
                'services.lokasi_id', DB::raw('DATE(services.created_at) as tgl'), 
                DB::raw('(service_details.quantity * service_details.price) as omset'), 
                DB::raw('(service_details.quantity * service_details.cost_price) as hpp'),
                'service_details.quantity as qty', 'service_details.barang_id', DB::raw('NULL as item_code')
            )
            ->where('services.service_order', 'LIKE', '%service%')->whereNotNull('service_details.barang_id')
            ->whereBetween(DB::raw('DATE(services.created_at)'), [$startDate, $endDate])
            ->when($filterLokasi !== 'all', function($q) use ($filterLokasi) { return $q->where('services.lokasi_id', $filterLokasi); })
            ->when($filterNonYgp !== 'all', function($q) use ($filterNonYgp) { 
                if ($filterNonYgp === 'none') return $q->whereRaw('1 = 0');
                return $q->where('service_details.barang_id', $filterNonYgp); 
            });

        // [MODIFIKASI SOP] Ganti filter whereBetween dan kolom select dari reg_date menjadi DATE(created_at)
        $serviceYgpQuery = DB::table('service_details')
            ->join('services', 'service_details.service_id', '=', 'services.id')
            ->join('parts', 'service_details.item_code', '=', 'parts.kode_part') 
            ->select(
                'services.lokasi_id', DB::raw('DATE(services.created_at) as tgl'), 
                DB::raw('(service_details.quantity * service_details.price) as omset'), 
                DB::raw('(service_details.quantity * service_details.cost_price) as hpp'),
                'service_details.quantity as qty', 'service_details.barang_id', 'service_details.item_code'
            )
            ->where('services.service_order', 'LIKE', '%service%')
            ->whereBetween(DB::raw('DATE(services.created_at)'), [$startDate, $endDate])
            ->when($filterLokasi !== 'all', function($q) use ($filterLokasi) { return $q->where('services.lokasi_id', $filterLokasi); })
            ->when($filterYgp !== 'all', function($q) use ($filterYgp) { 
                if ($filterYgp === 'none') return $q->whereRaw('1 = 0');
                return $q->where('service_details.item_code', $filterYgp); 
            });

        $dataRetail = collect($retailNonYgpQuery->unionAll($retailYgpQuery)->get());
        $dataService = collect($serviceNonYgpQuery->unionAll($serviceYgpQuery)->get());
        
        $prevRetailNonYgpQuery = DB::table('penjualan_details')->join('penjualans', 'penjualan_details.penjualan_id', '=', 'penjualans.id')
            ->select('penjualan_details.qty_jual as qty', 'penjualan_details.subtotal as omset')
            ->whereNotNull('penjualan_details.barang_id')->whereBetween('penjualans.tanggal_jual', [$prevStartDate, $prevEndDate])
            ->when($filterLokasi !== 'all', function($q) use ($filterLokasi) { return $q->where('penjualans.lokasi_id', $filterLokasi); })
            ->when($filterNonYgp !== 'all', function($q) use ($filterNonYgp) { 
                if ($filterNonYgp === 'none') return $q->whereRaw('1 = 0');
                return $q->where('penjualan_details.barang_id', $filterNonYgp); 
            });

        // [MODIFIKASI SOP] Ganti whereBetween dari reg_date menjadi DATE(created_at)
        $prevRetailYgpQuery = DB::table('service_details')->join('services', 'service_details.service_id', '=', 'services.id')
            ->select('service_details.quantity as qty', DB::raw('(service_details.quantity * service_details.price) as omset'))
            ->where('services.service_order', 'Part Retail')
            ->whereBetween(DB::raw('DATE(services.created_at)'), [$prevStartDate, $prevEndDate])
            ->when($filterLokasi !== 'all', function($q) use ($filterLokasi) { return $q->where('services.lokasi_id', $filterLokasi); })
            ->when($filterYgp !== 'all', function($q) use ($filterYgp) { 
                if ($filterYgp === 'none') return $q->whereRaw('1 = 0');
                return $q->where('service_details.item_code', $filterYgp); 
            });

        // [MODIFIKASI SOP] Ganti whereBetween dari reg_date menjadi DATE(created_at)
        $prevServiceNonYgpQuery = DB::table('service_details')->join('services', 'service_details.service_id', '=', 'services.id')
            ->select('service_details.quantity as qty', DB::raw('(service_details.quantity * service_details.price) as omset'))
            ->where('services.service_order', 'LIKE', '%service%')->whereNotNull('service_details.barang_id')
            ->whereBetween(DB::raw('DATE(services.created_at)'), [$prevStartDate, $prevEndDate])
            ->when($filterLokasi !== 'all', function($q) use ($filterLokasi) { return $q->where('services.lokasi_id', $filterLokasi); })
            ->when($filterNonYgp !== 'all', function($q) use ($filterNonYgp) { 
                if ($filterNonYgp === 'none') return $q->whereRaw('1 = 0');
                return $q->where('service_details.barang_id', $filterNonYgp); 
            });

        // [MODIFIKASI SOP] Ganti whereBetween dari reg_date menjadi DATE(created_at)
        $prevServiceYgpQuery = DB::table('service_details')->join('services', 'service_details.service_id', '=', 'services.id')
            ->join('parts', 'service_details.item_code', '=', 'parts.kode_part')
            ->select('service_details.quantity as qty', DB::raw('(service_details.quantity * service_details.price) as omset'))
            ->where('services.service_order', 'LIKE', '%service%')
            ->whereBetween(DB::raw('DATE(services.created_at)'), [$prevStartDate, $prevEndDate])
            ->when($filterLokasi !== 'all', function($q) use ($filterLokasi) { return $q->where('services.lokasi_id', $filterLokasi); })
            ->when($filterYgp !== 'all', function($q) use ($filterYgp) { 
                if ($filterYgp === 'none') return $q->whereRaw('1 = 0');
                return $q->where('service_details.item_code', $filterYgp); 
            });

        $prevDataRetail = collect($prevRetailNonYgpQuery->unionAll($prevRetailYgpQuery)->get());
        $prevDataService = collect($prevServiceNonYgpQuery->unionAll($prevServiceYgpQuery)->get());

        // Pengumpulan Nilai Mentah (Bruto / Kotor)
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

        // =========================================================================
        // LOGIKA PERHITUNGAN KEUANGAN NETTO (BERSIH)
        // =========================================================================
        // 1. Ambil Total Diskon Kasir (Retail POS) yang tersimpan di Header
        $totalDiskonRetail = DB::table('penjualans')
            ->whereBetween('tanggal_jual', [$startDate, $endDate])
            ->when($filterLokasi !== 'all', function($q) use ($filterLokasi) { return $q->where('lokasi_id', $filterLokasi); })
            ->sum('total_diskon');

        // 2. Omset Keseluruhan (Retail Bruto dikurangi Diskon Kasir + Service Netto Excel)
        $grandTotalOmset = ($totalRetailOmset - $totalDiskonRetail) + $totalServiceOmset;
        
        // 3. Menghitung Laba Retail & Laba Service secara terpisah lalu digabung
        $labaRetailBersih = ($totalRetailOmset - $totalRetailHpp) - $totalDiskonRetail;
        $labaServiceBersih = ($totalServiceOmset - $totalServiceHpp);
        $grandTotalLaba = $labaRetailBersih + $labaServiceBersih;
        
        // 4. Kalkulasi Akhir
        $grandTotalQty = $totalRetailQty + $totalServiceQty;
        $grossProfitMargin = $grandTotalOmset > 0 ? round(($grandTotalLaba / $grandTotalOmset) * 100, 2) : 0;
        // =========================================================================

        $omsetPie = ['retail' => ($totalRetailOmset - $totalDiskonRetail), 'service' => $totalServiceOmset];
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
            
            // Catatan: Chart masih menampilkan tren Bruto harian untuk kemudahan teknis
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
        $request = request();
        $lokasiId = $user->lokasi_id;
        
        $request->merge(['lokasi_id' => $lokasiId]);
        $data = $this->getPicData($request);
        
        $data['lokasi'] = Lokasi::find($lokasiId);
        
        $data['kcPendingAdjustmentsCount'] = StockAdjustment::where('status', 'PENDING_APPROVAL')
            ->where('lokasi_id', $lokasiId)->count();
            
        $data['kcPendingMutationsCount'] = StockMutation::where('status', 'PENDING_APPROVAL')
            ->where('lokasi_asal_id', $lokasiId)->count(); 
            
        $data['kcPendingAdjustments'] = StockAdjustment::where('status', 'PENDING_APPROVAL')
            ->where('lokasi_id', $lokasiId)->with('barang')->latest()->take(5)->get();
            
        $data['kcPendingMutations'] = StockMutation::where('status', 'PENDING_APPROVAL')
            ->where('lokasi_asal_id', $lokasiId)->with('barang', 'lokasiTujuan')->latest()->take(5)->get();

        return $data;
    }

    private function getAdminGudangData($user)
    {
        $lokasiId = $user->lokasi_id;

        $pendingApprovalPO = PurchaseOrder::where('status', 'PENDING_APPROVAL')
            ->where('po_type', 'dealer_request')
            ->where('sumber_lokasi_id', $lokasiId) 
            ->count();

        $readyToReceivePO = PurchaseOrder::where('lokasi_id', $lokasiId)
            ->whereIn('status', ['APPROVED', 'PARTIALLY_RECEIVED'])
            ->count();

        $pendingQC = Receiving::where('lokasi_id', $lokasiId)
            ->where('status', 'PENDING_QC')
            ->count();

        $pendingPutaway = Receiving::where('lokasi_id', $lokasiId)
            ->whereIn('status', ['QC_PASSED', 'PENDING_PUTAWAY'])
            ->count();

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

    private function getKasirData($user)
    {
        $lokasiId = $user->lokasi_id;
        
        $serviceToday = DB::table('services')
            ->where('lokasi_id', $lokasiId)
            ->where('service_order', '!=', 'Part Retail')
            ->whereDate('created_at', today())
            ->count();
            
        $salesToday = DB::table('services')
            ->where('lokasi_id', $lokasiId)
            ->where('service_order', 'Part Retail')
            ->whereDate('created_at', today())
            ->count();

        $revenueSalesToday = Penjualan::where('lokasi_id', $lokasiId)->whereDate('tanggal_jual', today())->sum('total_harga');
        
        // [MODIFIKASI SOP] Ganti whereDate dari reg_date menjadi created_at
        $revenueServiceToday = DB::table('services')
            ->where('lokasi_id', $lokasiId)
            ->whereDate('created_at', today())
            ->sum(DB::raw('COALESCE(total_payment, total_amount, 0)'));
            
        $grandTotalRevenueToday = $revenueSalesToday + $revenueServiceToday;

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

        $recentPenjualan = Penjualan::where('lokasi_id', $lokasiId)->latest('created_at')->take(5)->get()->map(function($item) {
            $item->jenis = 'Retail (POS)';
            $item->nomor = $item->nomor_faktur;
            $item->total = $item->total_harga;
            return $item;
        });
        
        $recentService = Service::where('lokasi_id', $lokasiId)->latest('created_at')->take(5)->get()->map(function($item) {
            $item->jenis = ($item->service_order == 'Part Retail') ? 'Part Retail' : 'Service';
            $item->nomor = $item->invoice_no;
            $item->total = $item->total_payment ?? $item->total_amount ?? 0;
            return $item;
        });

        $recentTransactions = $recentPenjualan->concat($recentService)->sortByDesc('created_at')->take(10);

        return compact('serviceToday', 'salesToday', 'grandTotalRevenueToday', 'totalItemsSoldMonth', 'lokasiId', 'recentTransactions');
    }

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
            ->where('lokasi.tipe', '=', 'DEALER') 
            ->select('lokasi.nama_lokasi', 'barangs.part_name', 'barangs.part_code', 'barangs.stok_minimum', DB::raw('SUM(inventory_batches.quantity) as total_qty'))
            ->groupBy('lokasi.id', 'lokasi.nama_lokasi', 'barangs.id', 'barangs.part_name', 'barangs.part_code', 'barangs.stok_minimum')
            ->orderByRaw('(SUM(inventory_batches.quantity) < barangs.stok_minimum) DESC')
            ->orderBy('lokasi.nama_lokasi')
            ->limit(20)
            ->get();
            
        $totalStokCount = DB::table('inventory_batches')
            ->join('lokasi', 'inventory_batches.lokasi_id', '=', 'lokasi.id')
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
        $request = request(); 
        
        $data = $this->getPicData($request);

        $data['stockData'] = DB::table('inventory_batches')
            ->join('barangs', 'inventory_batches.barang_id', '=', 'barangs.id')
            ->join('lokasi', 'inventory_batches.lokasi_id', '=', 'lokasi.id') 
            ->where('lokasi.tipe', '=', 'DEALER')
            ->select('lokasi.nama_lokasi', 'barangs.part_name', 'barangs.part_code', 'barangs.stok_minimum', DB::raw('SUM(inventory_batches.quantity) as total_qty'))
            ->groupBy('lokasi.id', 'lokasi.nama_lokasi', 'barangs.id', 'barangs.part_name', 'barangs.part_code', 'barangs.stok_minimum')
            ->orderByRaw('(SUM(inventory_batches.quantity) < barangs.stok_minimum) DESC')
            ->orderBy('lokasi.nama_lokasi')
            ->limit(20)
            ->get();

        return $data;
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