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
    public function stockCard(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $movements = collect();
        $lokasis = collect();
        $selectedLokasiId = $request->input('lokasi_id');

        if (!$user->hasRole(['SA', 'PIC', 'MA', 'ACC', 'SMD'])) {
            if ($user->lokasi_id) {
                $lokasis = Lokasi::where('id', $user->lokasi_id)->get();
                $selectedLokasiId = $user->lokasi_id;
            }
        } else {
            // ++ PERBAIKAN: Hilangkan Gudang Pusat dari Dropdown ++
            $lokasis = Lokasi::where('is_active', true)
                             ->where('tipe', '!=', 'PUSAT')
                             ->orderBy('nama_lokasi')
                             ->get();
        }

        $barangs = Barang::orderBy('part_name')->get();

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        if ($request->filled('barang_id')) {
            $query = StockMovement::where('barang_id', $request->barang_id)
                ->with(['lokasi', 'user', 'barang'])
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate);

            if ($selectedLokasiId) {
                $query->where('lokasi_id', $selectedLokasiId);
            }

            $movements = $query->oldest()->get();
        }

        return view('admin.reports.stock_card', compact('barangs', 'lokasis', 'movements', 'startDate', 'endDate', 'selectedLokasiId'));
    }

    public function stockByWarehouse(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $inventoryItems = collect();
        $lokasis = collect();
        $selectedLokasiId = null;
        $selectedLokasiName = null;

        if ($user->hasRole(['KG', 'KC', 'AG', 'AD']) && $user->lokasi_id) {
            $lokasis = Lokasi::where('id', $user->lokasi_id)->get();
            if (!$request->filled('lokasi_id')) {
                $request->merge(['lokasi_id' => $user->lokasi_id]);
            }
        } else {
            // ++ PERBAIKAN: Hilangkan Gudang Pusat dari Dropdown ++
            $lokasis = Lokasi::where('is_active', true)
                             ->where('tipe', '!=', 'PUSAT')
                             ->orderBy('nama_lokasi')
                             ->get();
        }

        $barangs = Barang::orderBy('part_name')->get();

        if ($request->filled('lokasi_id')) {
            $selectedLokasiId = $request->lokasi_id;
            $lokasiObj = Lokasi::find($selectedLokasiId);
            $selectedLokasiName = $lokasiObj ? $lokasiObj->nama_lokasi : '-';

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
        $request->validate(['lokasi_id' => 'required|exists:lokasi,id']);
        $lokasi = Lokasi::find($request->lokasi_id);
        $fileName = 'Laporan Stok - ' . $lokasi->kode_lokasi . ' - ' . now()->format('d-m-Y') . '.xlsx';

        return Excel::download(new StockByWarehouseExport($request->lokasi_id), $fileName);
    }

    public function salesJournal(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        $query = PenjualanDetail::with(['penjualan.konsumen', 'penjualan.sales', 'barang'])
            ->whereHas('penjualan', function ($q) use ($startDate, $endDate, $user) {
                $q->whereBetween('tanggal_jual', [$startDate, $endDate]);

                if (!$user->hasRole(['SA', 'PIC', 'MA', 'ACC', 'ASD']) && $user->lokasi_id) {
                    $q->where('lokasi_id', $user->lokasi_id);
                }
            });

        $salesDetails = $query->latest()->get();

        return view('admin.reports.sales_journal', compact('salesDetails', 'startDate', 'endDate'));
    }

    public function exportSalesJournal(Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $fileName = 'Jurnal Penjualan - ' . $startDate . ' sampai ' . $endDate . '.xlsx';

        return Excel::download(new SalesJournalExport($startDate, $endDate), $fileName);
    }

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

                if (!$user->hasRole(['SA', 'PIC', 'MA', 'ACC']) && $user->lokasi_id) {
                    $q->where('lokasi_id', $user->lokasi_id);
                }
            });

        $purchaseDetails = $query->latest()->get();

        return view('admin.reports.purchase_journal', compact('purchaseDetails', 'startDate', 'endDate'));
    }

    public function exportPurchaseJournal(Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $fileName = 'Jurnal Pembelian - ' . $startDate . ' sampai ' . $endDate . '.xlsx';

        return Excel::download(new PurchaseJournalExport($startDate, $endDate), $fileName);
    }

    public function inventoryValue()
    {
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

        if (!$user->hasRole(['SA', 'PIC', 'MA', 'ACC']) && $user->lokasi_id) {
            $inventoryQuery->where('lokasi_id', $user->lokasi_id);
        }

        $inventoryDetails = $inventoryQuery->get();

        // PERUBAHAN: Gunakan 'selling_out' untuk perhitungan nilai total
        $totalValue = $inventoryDetails->sum(function($item) {
            if ($item->barang) {
                // Menggunakan selling_out sesuai permintaan
                return $item->quantity * $item->barang->selling_out;
            }
            return 0;
        });

        return view('admin.reports.inventory_value', compact('inventoryDetails', 'totalValue'));
    }

    public function exportInventoryValue()
    {
        $fileName = 'Laporan Nilai Persediaan - ' . now()->format('d-m-Y') . '.xlsx';
        return Excel::download(new InventoryValueExport(), $fileName);
    }

    public function salesPurchaseAnalysis(Request $request)
    {
        $startDate = $request->input('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        $topSellingParts = PenjualanDetail::select('barang_id', DB::raw('SUM(qty_jual) as total_qty'))
            ->with('barang')
            ->whereHas('penjualan', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('tanggal_jual', [$startDate, $endDate]);
            })
            ->groupBy('barang_id')
            ->orderBy('total_qty', 'desc')
            ->limit(10)
            ->get();

        $topPurchasedParts = ReceivingDetail::select('barang_id', DB::raw('SUM(qty_terima) as total_qty'))
            ->with('barang')
            ->whereHas('receiving', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('tanggal_terima', [$startDate, $endDate]);
            })
            ->groupBy('barang_id')
            ->orderBy('total_qty', 'desc')
            ->limit(10)
            ->get();

        $salesByCategory = collect();

        return view('admin.reports.sales_purchase_analysis', compact(
            'topSellingParts',
            'topPurchasedParts',
            'salesByCategory',
            'startDate',
            'endDate'
        ));
    }

    public function stockReport()
    {
        $inventoryDetails = InventoryBatch::select(
                'barang_id',
                'lokasi_id',
                'rak_id',
                DB::raw('SUM(quantity) as quantity')
            )
            ->where('quantity', '>', 0)
            ->with(['barang', 'lokasi', 'rak'])
            ->groupBy('barang_id', 'lokasi_id', 'rak_id')
            ->get()
            ->sortBy(['barang.part_name', 'lokasi.nama_lokasi']);

        return view('admin.reports.stock_report', compact('inventoryDetails'));
    }

    public function exportStockReport()
    {
        $fileName = 'Laporan Stok Total (Semua Lokasi) - ' . now()->format('d-m-Y') . '.xlsx';
        return Excel::download(new StockReportExport(), $fileName);
    }

    public function exportStockCard(Request $request)
    {
        $request->validate([
            'barang_id' => 'required|exists:barangs,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'lokasi_id' => 'nullable|exists:lokasi,id'
        ]);

        $barang = Barang::findOrFail($request->barang_id);
        $fileName = 'Kartu Stok - ' . $barang->part_code . ' - ' . $request->start_date . ' sampai ' . $request->end_date . '.xlsx';

        return Excel::download(new StockCardExport(
            $request->barang_id,
            $request->lokasi_id,
            $request->start_date,
            $request->end_date
        ), $fileName);
    }

    public function salesSummary(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $selectedLokasiId = $request->input('dealer_id');

        $dealerList = collect();
        $isRestrictedUser = !$user->hasRole(['SA', 'PIC', 'MA', 'ASD', 'ACC']) && $user->lokasi_id;

        if ($isRestrictedUser) {
            $dealerList = Lokasi::where('id', $user->lokasi_id)->get();
            $selectedLokasiId = $user->lokasi_id;
        } else {
            // ++ PERBAIKAN: Hilangkan Gudang Pusat dari Dropdown ++
            $dealerList = Lokasi::where('is_active', true)
                                ->where('tipe', '!=', 'PUSAT')
                                ->orderBy('nama_lokasi')
                                ->get();
        }

        $query = PenjualanDetail::with(['penjualan.lokasi', 'penjualan.konsumen', 'barang'])
            ->whereHas('penjualan', function ($q) use ($startDate, $endDate, $selectedLokasiId, $user) {
                $q->whereBetween('tanggal_jual', [$startDate, $endDate]);
                if ($selectedLokasiId) {
                    $q->where('lokasi_id', $selectedLokasiId);
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
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $dealerId = $request->input('dealer_id');
        $fileName = 'Laporan Penjualan - ' . $startDate . ' sampai ' . $endDate . '.xlsx';

        return Excel::download(new SalesSummaryExport($startDate, $endDate, $dealerId), $fileName);
    }

    public function serviceSummary(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // --- 1. LOGIKA STICKY FILTER (Sama seperti Menu Service) ---
        // Jika user melakukan filter manual, simpan ke session
        if ($request->filled('start_date') || $request->filled('end_date')) {
            session([
                'report.service.start_date' => $request->input('start_date'),
                'report.service.end_date' => $request->input('end_date'),
            ]);
        }

        // Ambil dari Request -> Session -> Default Hari Ini
        $startDate = $request->input('start_date', session('report.service.start_date', now()->toDateString()));
        $endDate = $request->input('end_date', session('report.service.end_date', now()->toDateString()));
        
        $invoiceNo = $request->input('invoice_no');
        // -----------------------------------------------------------

        $query = DB::table('service_details')
            ->join('services', 'service_details.service_id', '=', 'services.id')
            ->leftJoin('barangs', 'service_details.barang_id', '=', 'barangs.id')
            ->select(
                'service_details.item_code',
                'service_details.item_name',
                'service_details.item_category',
                DB::raw('SUM(service_details.quantity) as total_qty'),
                // Penjualan = Harga Part + Jasa (jika ada jasa nempel)
                DB::raw('SUM(service_details.price + service_details.labor_cost_service) as total_penjualan'),
                // Modal = Qty * Harga Beli (selling_out/in sesuai kebijakan)
                DB::raw("SUM(service_details.quantity * COALESCE(barangs.selling_out, 0)) as total_modal"),
                // Profit
                DB::raw("SUM(service_details.price + service_details.labor_cost_service) -
                         SUM(service_details.quantity * COALESCE(barangs.selling_out, 0)) as total_keuntungan")
            )
            ->whereBetween('services.reg_date', [$startDate, $endDate])
            // --- FILTER KHUSUS: HANYA BARANG (PARTS ONLY) ---
            ->whereNotNull('service_details.barang_id')
            // ------------------------------------------------
            ->groupBy('service_details.item_code', 'service_details.item_name', 'service_details.item_category')
            ->orderBy('total_qty', 'desc');

        if (!$user->hasRole(['SA', 'PIC', 'MA', 'ACC']) && $user->lokasi_id) {
            $query->where('services.lokasi_id', $user->lokasi_id);
        }

        if ($invoiceNo) {
            $query->where('services.invoice_no', 'like', '%' . $invoiceNo . '%');
        }

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
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $invoiceNo = $request->input('invoice_no');
        $fileName = 'Laporan Service - ' . $startDate . ' sampai ' . $endDate . '.xlsx';

        return Excel::download(new ServiceSummaryExport($startDate, $endDate, $invoiceNo), $fileName);
    }
}
