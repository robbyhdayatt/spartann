<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Barang;
use App\Models\StockMovement;
use App\Models\Lokasi;
use App\Models\InventoryBatch;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\StockByWarehouseExport;
use App\Exports\SalesJournalExport;
use App\Models\PenjualanDetail;
use App\Models\Penjualan;
use App\Exports\PurchaseJournalExport;
use App\Models\ReceivingDetail;
use App\Exports\InventoryValueExport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Exports\StockCardExport;
use App\Exports\SalesSummaryExport;
use App\Exports\ServiceSummaryExport;
use App\Exports\StockReportExport;

class ReportController extends Controller
{
    // =================================================================
    // 1. KARTU STOK
    // =================================================================
    public function stockCard(Request $request)
    {
        $this->authorize('view-stock-card');

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $movements = collect();
        $lokasis = collect();
        $selectedLokasiId = $request->input('lokasi_id');
        
        if ($user->isGlobal()) {
            $lokasis = Lokasi::where('is_active', true)->orderBy('nama_lokasi')->get();
        } 
        elseif ($user->isPusat()) {
            $lokasis = Lokasi::where('tipe', 'DEALER')->where('is_active', true)->orderBy('nama_lokasi')->get();
        } 
        elseif ($user->isGudang() || $user->isDealer()) {
            $lokasis = Lokasi::where('id', $user->lokasi_id)->get();
            $selectedLokasiId = $user->lokasi_id;
        }

        $barangs = Barang::where('is_active', true)->orderBy('part_name')->get();

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        if ($request->filled('barang_id')) {
            $query = StockMovement::where('barang_id', $request->barang_id)
                ->with(['lokasi', 'user', 'barang'])
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate);

            if ($selectedLokasiId) {
                $query->where('lokasi_id', $selectedLokasiId);
            } else {
                if ($user->isPusat()) {
                    $query->whereHas('lokasi', fn($q) => $q->where('tipe', 'DEALER'));
                }
            }

            $movements = $query->oldest()->get();
        }

        return view('admin.reports.stock_card', compact('barangs', 'lokasis', 'movements', 'startDate', 'endDate', 'selectedLokasiId'));
    }

    public function exportStockCard(Request $request)
    {
        $this->authorize('view-stock-card');

        $request->validate([
            'barang_id' => 'required|exists:barangs,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'lokasi_id' => 'nullable|exists:lokasi,id'
        ]);

        $user = Auth::user();
        if (!$user->isGlobal()) {
            if (($user->isGudang() || $user->isDealer()) && $request->lokasi_id != $user->lokasi_id) {
                abort(403, 'Akses Ditolak');
            }
        }

        $barang = Barang::findOrFail($request->barang_id);
        $fileName = 'Kartu Stok - ' . $barang->part_code . ' - ' . $request->start_date . ' sampai ' . $request->end_date . '.xlsx';

        return Excel::download(new StockCardExport(
            $request->barang_id,
            $request->lokasi_id,
            $request->start_date,
            $request->end_date
        ), $fileName);
    }

    // =================================================================
    // 2. STOK PER LOKASI
    // =================================================================
    public function stockByWarehouse(Request $request)
    {
        $this->authorize('view-stock-location-report');

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $inventoryItems = collect();
        $lokasis = collect();
        $selectedLokasiId = null;
        $selectedLokasiName = null;
        if ($user->isGlobal()) {
            $lokasis = Lokasi::where('is_active', true)->orderBy('nama_lokasi')->get();
        } elseif ($user->isPusat()) {
            $lokasis = Lokasi::where('tipe', 'DEALER')->orderBy('nama_lokasi')->get();
        } else {
            $lokasis = Lokasi::where('id', $user->lokasi_id)->get();
            $request->merge(['lokasi_id' => $user->lokasi_id]);
        }

        $barangs = Barang::where('is_active', true)->orderBy('part_name')->get();

        if ($request->filled('lokasi_id')) {
            $selectedLokasiId = $request->lokasi_id;
            $targetLokasi = Lokasi::find($selectedLokasiId);
            if ($user->isPusat() && $targetLokasi->tipe !== 'DEALER') abort(403);
            if (($user->isGudang() || $user->isDealer()) && $selectedLokasiId != $user->lokasi_id) abort(403);

            $selectedLokasiName = $targetLokasi ? $targetLokasi->nama_lokasi : '-';

            $inventoryItems = InventoryBatch::select(
                    'barang_id',
                    'rak_id',
                    'lokasi_id',
                    DB::raw('SUM(quantity) as quantity')
                )
                ->where('lokasi_id', $selectedLokasiId)
                ->where('quantity', '>', 0)
                ->with([
                    'barang',
                    'rak', 
                    'lokasi'
                ])
                ->groupBy('barang_id', 'rak_id', 'lokasi_id')
                ->get()
                ->sortBy('barang.part_name');
        }

        return view('admin.reports.stock_by_warehouse', compact(
            'inventoryItems',
            'lokasis',
            'selectedLokasiId',
            'selectedLokasiName',
            'barangs'
        ));
    }

    public function exportStockByWarehouse(Request $request)
    {
        $this->authorize('view-stock-location-report');
        $request->validate(['lokasi_id' => 'required|exists:lokasi,id']);
        
        $user = Auth::user();
        if (($user->isGudang() || $user->isDealer()) && $request->lokasi_id != $user->lokasi_id) abort(403);

        $lokasi = Lokasi::find($request->lokasi_id);
        $fileName = 'Laporan Stok - ' . $lokasi->kode_lokasi . ' - ' . now()->format('d-m-Y') . '.xlsx';

        $canSeeSellingIn = $user->can('view-price-selling-in');
        $canSeeSellingOut = $user->can('view-price-selling-out');

        return Excel::download(new StockByWarehouseExport($request->lokasi_id, $canSeeSellingIn, $canSeeSellingOut), $fileName);
    }

    // =================================================================
    // 3. LAPORAN STOK TOTAL (GLOBAL)
    // =================================================================
    public function stockReport()
    {
        $this->authorize('view-stock-total-report');

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $query = InventoryBatch::select(
                'barang_id',
                'lokasi_id',
                'rak_id',
                DB::raw('SUM(quantity) as quantity')
            )
            ->where('quantity', '>', 0)
            ->with(['barang', 'lokasi', 'rak'])
            ->groupBy('barang_id', 'lokasi_id', 'rak_id');

        $inventoryDetails = $query->get()->sortBy(['barang.part_name', 'lokasi.nama_lokasi']);

        return view('admin.reports.stock_report', compact('inventoryDetails'));
    }

    public function exportStockReport()
    {
        $this->authorize('view-stock-total-report');
        $fileName = 'Laporan Stok Total (Semua Lokasi) - ' . now()->format('d-m-Y') . '.xlsx';
        
        $user = Auth::user();
        $canSeeSellingIn = $user->can('view-price-selling-in');
        $canSeeSellingOut = $user->can('view-price-selling-out');

        return Excel::download(new StockReportExport($canSeeSellingIn, $canSeeSellingOut), $fileName);
    }

    // =================================================================
    // 4. JURNAL PENJUALAN
    // =================================================================
    public function salesJournal(Request $request)
    {
        $this->authorize('view-sales-report');

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        $query = PenjualanDetail::with(['penjualan.konsumen', 'penjualan.sales', 'barang'])
            ->whereHas('penjualan', function ($q) use ($startDate, $endDate, $user) {
                $q->whereBetween('tanggal_jual', [$startDate, $endDate]);

                if ($user->isGlobal()) {
                } elseif ($user->isPusat()) {
                    $q->whereHas('lokasi', fn($l) => $l->where('tipe', 'DEALER'));
                } elseif ($user->isDealer()) {
                    $q->where('lokasi_id', $user->lokasi_id);
                } else {
                    $q->whereRaw('1=0');
                }
            });

        $salesDetails = $query->latest()->get();

        return view('admin.reports.sales_journal', compact('salesDetails', 'startDate', 'endDate'));
    }

    public function exportSalesJournal(Request $request)
    {
        $this->authorize('view-sales-report');
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $fileName = 'Jurnal Penjualan - ' . $startDate . ' sampai ' . $endDate . '.xlsx';

        return Excel::download(new SalesJournalExport($startDate, $endDate), $fileName);
    }

    // =================================================================
    // 5. JURNAL PEMBELIAN
    // =================================================================
    public function purchaseJournal(Request $request)
    {
        $this->authorize('view-purchase-journal');

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        $query = ReceivingDetail::with(['receiving.purchaseOrder.supplier', 'barang'])
            ->whereHas('receiving', function ($q) use ($startDate, $endDate, $user) {
                $q->whereBetween('tanggal_terima', [$startDate, $endDate]);

                if ($user->isGlobal()) {
                } elseif ($user->isPusat()) {
                    $q->whereHas('lokasi', fn($l) => $l->where('tipe', 'DEALER'));
                } elseif ($user->isGudang()) {
                    $q->where('lokasi_id', $user->lokasi_id);
                } else {
                    $q->where('lokasi_id', $user->lokasi_id);
                }
            });

        $purchaseDetails = $query->latest()->get();

        return view('admin.reports.purchase_journal', compact('purchaseDetails', 'startDate', 'endDate'));
    }

    public function exportPurchaseJournal(Request $request)
    {
        $this->authorize('view-purchase-journal');
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $fileName = 'Jurnal Pembelian - ' . $startDate . ' sampai ' . $endDate . '.xlsx';

        return Excel::download(new PurchaseJournalExport($startDate, $endDate), $fileName);
    }

    // =================================================================
    // 6. NILAI PERSEDIAAN
    // =================================================================
    public function inventoryValue()
    {
        $this->authorize('view-inventory-value-report');

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $inventoryQuery = InventoryBatch::select(
                'barang_id',
                'lokasi_id',
                'rak_id',
                DB::raw('SUM(quantity) as quantity')
            )
            ->where('quantity', '>', 0)
            ->with(['barang', 'lokasi', 'rak'])
            ->groupBy('barang_id', 'lokasi_id', 'rak_id');

        if ($user->isPusat()) {
            $inventoryQuery->whereHas('lokasi', fn($q) => $q->where('tipe', 'DEALER'));
        } elseif ($user->isGudang() || $user->isDealer()) {
            $inventoryQuery->where('lokasi_id', $user->lokasi_id);
        }

        $inventoryDetails = $inventoryQuery->get();
        
        // Cek Gate Permission
        $canSeeSellingIn = $user->can('view-price-selling-in');
        
        $totalValue = $inventoryDetails->sum(function($item) use ($canSeeSellingIn) {
            if ($item->barang) {
                // Jika boleh lihat Selling In (Gudang/SA), pakai Selling In.
                // Jika tidak (Pusat/Dealer), pakai Selling Out.
                if ($canSeeSellingIn) {
                    return $item->quantity * $item->barang->selling_in;
                } else {
                    return $item->quantity * $item->barang->selling_out;
                }
            }
            return 0;
        });

        // Kirim $canSeeSellingIn ke view agar view juga menyesuaikan nama kolom
        return view('admin.reports.inventory_value', compact('inventoryDetails', 'totalValue', 'canSeeSellingIn'));
    }

    public function exportInventoryValue()
    {
        $this->authorize('view-inventory-value-report');
        $fileName = 'Laporan Nilai Persediaan - ' . now()->format('d-m-Y') . '.xlsx';
        
        // Cek Gate dan kirim boolean ke Export class
        $canSeeSellingIn = Auth::user()->can('view-price-selling-in');
        
        return Excel::download(new InventoryValueExport($canSeeSellingIn), $fileName);
    }

    // =================================================================
    // 7. SALES SUMMARY
    // =================================================================
    public function salesSummary(Request $request)
    {
        $this->authorize('view-sales-report');

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $selectedLokasiId = $request->input('dealer_id');

        $dealerList = collect();
        if ($user->isGlobal() || $user->isPusat()) {
            $dealerList = Lokasi::where('tipe', 'DEALER')->orderBy('nama_lokasi')->get();
        } else {
            $dealerList = Lokasi::where('id', $user->lokasi_id)->get();
            $selectedLokasiId = $user->lokasi_id;
        }

        $query = PenjualanDetail::with(['penjualan.lokasi', 'penjualan.konsumen', 'barang'])
            ->whereHas('penjualan', function ($q) use ($startDate, $endDate, $selectedLokasiId, $user) {
                $q->whereBetween('tanggal_jual', [$startDate, $endDate]);
                
                if ($selectedLokasiId) {
                    $q->where('lokasi_id', $selectedLokasiId);
                } elseif ($user->isPusat()) {
                    $q->whereHas('lokasi', fn($l) => $l->where('tipe', 'DEALER'));
                }
            })
            ->orderByDesc('created_at');

        $reportData = $query->get();

        $grandTotalQty = 0;
        $grandTotalPenjualan = 0;
        $grandTotalModal = 0;
        $grandTotalKeuntungan = 0;

        foreach ($reportData as $data) {
            $modal_satuan = $data->barang->selling_out ?? 0;
            $total_modal_item = $data->qty_jual * $modal_satuan;
            $total_keuntungan_item = $data->subtotal - $total_modal_item;

            $grandTotalQty += $data->qty_jual;
            $grandTotalPenjualan += $data->subtotal;
            $grandTotalModal += $total_modal_item;
            $grandTotalKeuntungan += $total_keuntungan_item;
        }

        return view('admin.reports.sales_summary', compact(
            'reportData',
            'grandTotalQty',
            'grandTotalPenjualan',
            'grandTotalModal',
            'grandTotalKeuntungan',
            'startDate',
            'endDate',
            'dealerList',
            'selectedLokasiId'
        ));
    }

    public function exportSalesSummary(Request $request)
    {
        $this->authorize('view-sales-report');
        // ... Logic export sama dengan index ...
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $dealerId = $request->input('dealer_id');
        
        $fileName = 'Laporan Penjualan - ' . $startDate . ' sampai ' . $endDate . '.xlsx';
        return Excel::download(new SalesSummaryExport($startDate, $endDate, $dealerId), $fileName);
    }

    // =================================================================
    // 8. SERVICE SUMMARY
    // =================================================================
    public function serviceSummary(Request $request)
    {
        $this->authorize('view-service-report');

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($request->filled('start_date') || $request->filled('end_date')) {
            session([
                'report.service.start_date' => $request->input('start_date'),
                'report.service.end_date' => $request->input('end_date'),
            ]);
        }

        $startDate = $request->input('start_date', session('report.service.start_date', now()->startOfMonth()->toDateString()));
        $endDate = $request->input('end_date', session('report.service.end_date', now()->endOfMonth()->toDateString()));
        $invoiceNo = $request->input('invoice_no');
        $validPartCodes = DB::table('converts_main')->distinct()->pluck('part_code');

        $query = DB::table('stock_movements')
            ->join('barangs', 'stock_movements.barang_id', '=', 'barangs.id')
            ->join('services', 'stock_movements.referensi_id', '=', 'services.id')
            ->where('stock_movements.referensi_type', 'like', '%Service%')
            ->whereIn('barangs.part_code', $validPartCodes)
            ->select(
                'barangs.part_code as item_code',
                'barangs.part_name as item_name',
                DB::raw("'Sparepart' as item_category"),
                DB::raw('ABS(SUM(stock_movements.jumlah)) as total_qty'),
                DB::raw('ABS(SUM(stock_movements.jumlah)) * MAX(COALESCE(barangs.retail, 0)) as total_penjualan'),
                DB::raw('ABS(SUM(stock_movements.jumlah)) * MAX(COALESCE(barangs.selling_out, 0)) as total_modal'),
                DB::raw('(ABS(SUM(stock_movements.jumlah)) * MAX(COALESCE(barangs.retail, 0))) - 
                         (ABS(SUM(stock_movements.jumlah)) * MAX(COALESCE(barangs.selling_out, 0))) as total_keuntungan')
            )
            ->whereBetween('services.reg_date', [$startDate, $endDate])
            ->groupBy('barangs.id', 'barangs.part_code', 'barangs.part_name');

        if ($user->isGlobal()) {
        } elseif ($user->isPusat()) {
            $query->join('lokasi', 'services.lokasi_id', '=', 'lokasi.id')
                  ->where('lokasi.tipe', 'DEALER');
        } else {
            $query->where('services.lokasi_id', $user->lokasi_id);
        }

        if ($invoiceNo) {
            $query->where('services.invoice_no', 'like', '%' . $invoiceNo . '%');
        }

        $query->having('total_qty', '>', 0);
        $query->orderBy('total_qty', 'desc');

        $reportData = $query->get();

        $grandTotalQty = $reportData->sum('total_qty');
        $grandTotalPenjualan = $reportData->sum('total_penjualan');
        $grandTotalModal = $reportData->sum('total_modal');
        $grandTotalKeuntungan = $reportData->sum('total_keuntungan');

        return view('admin.reports.service_summary', compact(
            'reportData',
            'grandTotalQty',
            'grandTotalPenjualan',
            'grandTotalModal',
            'grandTotalKeuntungan',
            'startDate',
            'endDate',
            'invoiceNo'
        ));
    }

    public function exportServiceSummary(Request $request)
    {
        $this->authorize('view-service-report');
        $startDate = $request->input('start_date') ?? session('report.service.start_date') ?? now()->startOfMonth()->toDateString();
        $endDate = $request->input('end_date') ?? session('report.service.end_date') ?? now()->endOfMonth()->toDateString();
        $invoiceNo = $request->input('invoice_no');
        
        $lokasiId = null;
        if (!Auth::user()->isGlobal() && !Auth::user()->isPusat()) {
            $lokasiId = Auth::user()->lokasi_id;
        }

        $fileName = 'Laporan Service Summary (Parts) - ' . $startDate . ' sampai ' . $endDate . '.xlsx';
        return Excel::download(new ServiceSummaryExport($startDate, $endDate, $invoiceNo, $lokasiId), $fileName);
    }

    public function salesPurchaseAnalysis(Request $request)
    {
        return view('admin.home');
    }
}