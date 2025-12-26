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
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $movements = collect();
        $lokasis = collect();
        $selectedLokasiId = $request->input('lokasi_id');

        // Logic Filter Lokasi: Jika bukan orang Pusat/Manager, kunci ke lokasi sendiri
        $isRestrictedUser = !$user->hasRole(['SA', 'PIC', 'MA', 'ACC', 'ASD']);

        if ($isRestrictedUser && $user->lokasi_id) {
            $lokasis = Lokasi::where('id', $user->lokasi_id)->get();
            $selectedLokasiId = $user->lokasi_id;
        } else {
            // User Pusat: Tampilkan semua kecuali Pusat (opsional, tergantung kebutuhan)
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

    // =================================================================
    // 2. STOK PER LOKASI
    // =================================================================
    public function stockByWarehouse(Request $request)
    {
        /** @var \App\Models\User $user */
        $this->authorize('view-stock-by-warehouse');
        $user = Auth::user();
        $inventoryItems = collect();
        $lokasis = collect();
        $selectedLokasiId = null;
        $selectedLokasiName = null;

        // --- PERBAIKAN LOGIKA DISINI ---
        // Kita cek apakah user adalah staff dealer/gudang yang terikat lokasi
        // Tambahkan 'PC' (Part Counter) ke dalam list ini
        $isDealerUser = $user->hasRole(['KG', 'KC', 'AG', 'AD', 'PC', 'KSR']) && $user->lokasi_id;

        if ($isDealerUser) {
            // Jika User Dealer, paksa lokasi ke lokasi user
            $lokasis = Lokasi::where('id', $user->lokasi_id)->get();
            
            // Auto-select lokasi user agar query langsung jalan saat halaman dimuat
            // Ini yang memperbaiki masalah "tabel kosong" saat pertama buka
            if (!$request->filled('lokasi_id')) {
                $request->merge(['lokasi_id' => $user->lokasi_id]);
            }
        } else {
            // User Pusat: Bebas pilih
            $lokasis = Lokasi::where('is_active', true)
                             ->where('tipe', '!=', 'PUSAT')
                             ->orderBy('nama_lokasi')
                             ->get();
        }

        $barangs = Barang::orderBy('part_name')->get();

        // Query dijalankan jika lokasi_id terisi (baik dari input atau auto-merge di atas)
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

    // =================================================================
    // 3. LAPORAN STOK TOTAL (GLOBAL)
    // =================================================================
    public function stockReport()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Filter lokasi jika user terbatas (misal SMD dealer)
        $query = InventoryBatch::select(
                'barang_id',
                'lokasi_id',
                'rak_id',
                DB::raw('SUM(quantity) as quantity')
            )
            ->where('quantity', '>', 0)
            ->with(['barang', 'lokasi', 'rak'])
            ->groupBy('barang_id', 'lokasi_id', 'rak_id');

        if (!$user->hasRole(['SA', 'PIC', 'MA', 'ACC', 'AG', 'ASD']) && $user->lokasi_id) {
             $query->where('lokasi_id', $user->lokasi_id);
        }

        $inventoryDetails = $query->get()->sortBy(['barang.part_name', 'lokasi.nama_lokasi']);

        return view('admin.reports.stock_report', compact('inventoryDetails'));
    }

    public function exportStockReport()
    {
        $fileName = 'Laporan Stok Total (Semua Lokasi) - ' . now()->format('d-m-Y') . '.xlsx';
        return Excel::download(new StockReportExport(), $fileName);
    }

    // =================================================================
    // 4. JURNAL PENJUALAN
    // =================================================================
    public function salesJournal(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        $query = PenjualanDetail::with(['penjualan.konsumen', 'penjualan.sales', 'barang'])
            ->whereHas('penjualan', function ($q) use ($startDate, $endDate, $user) {
                $q->whereBetween('tanggal_jual', [$startDate, $endDate]);

                // Filter Role
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

    // =================================================================
    // 6. NILAI PERSEDIAAN
    // =================================================================
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

        $totalValue = $inventoryDetails->sum(function($item) {
            if ($item->barang) {
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

    // =================================================================
    // 7. SALES SUMMARY
    // =================================================================
    public function salesSummary(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $selectedLokasiId = $request->input('dealer_id');

        $dealerList = collect();
        // Tambahkan PC, KC, KSR agar hanya melihat lokasinya sendiri
        $isRestrictedUser = !$user->hasRole(['SA', 'PIC', 'MA', 'ASD', 'ACC']) && $user->lokasi_id;

        if ($isRestrictedUser) {
            $dealerList = Lokasi::where('id', $user->lokasi_id)->get();
            $selectedLokasiId = $user->lokasi_id;
        } else {
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
        /** @var \App\Models\User $user */
        $user = Auth::user();
        
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        
        $dealerId = $request->input('dealer_id');
        $isRestrictedUser = !$user->hasRole(['SA', 'PIC', 'MA', 'ASD', 'ACC']) && $user->lokasi_id;
        
        if ($isRestrictedUser) {
            $dealerId = $user->lokasi_id;
        }

        $fileName = 'Laporan Penjualan - ' . $startDate . ' sampai ' . $endDate . '.xlsx';

        return Excel::download(new SalesSummaryExport($startDate, $endDate, $dealerId), $fileName);
    }

    // =================================================================
    // 8. SERVICE SUMMARY
    // =================================================================
    public function serviceSummary(Request $request)
    {
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

        // Gunakan distinct agar listnya unik
        $validPartCodes = DB::table('converts_main')->distinct()->pluck('part_code');

        $query = DB::table('service_details')
            ->join('services', 'service_details.service_id', '=', 'services.id')
            ->join('barangs', 'service_details.barang_id', '=', 'barangs.id')
            ->whereIn('service_details.item_code', $validPartCodes)
            ->select(
                'service_details.item_code',
                'barangs.part_name as item_name',
                'service_details.item_category',
                DB::raw('SUM(service_details.quantity) as total_qty'),
                DB::raw('SUM(service_details.quantity * COALESCE(barangs.retail, 0)) as total_penjualan'),
                DB::raw("SUM(service_details.quantity * COALESCE(barangs.selling_out, 0)) as total_modal"),
                DB::raw("SUM(service_details.quantity * COALESCE(barangs.retail, 0)) -
                         SUM(service_details.quantity * COALESCE(barangs.selling_out, 0)) as total_keuntungan")
            )
            ->whereBetween('services.reg_date', [$startDate, $endDate])
            ->groupBy('service_details.item_code', 'barangs.part_name', 'service_details.item_category')
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
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $startDate = $request->input('start_date') ?? session('report.service.start_date') ?? now()->startOfMonth()->toDateString();
        $endDate = $request->input('end_date') ?? session('report.service.end_date') ?? now()->endOfMonth()->toDateString();
        $invoiceNo = $request->input('invoice_no');

        $lokasiId = null;
        if (!$user->hasRole(['SA', 'PIC', 'MA', 'ACC'])) {
            $lokasiId = $user->lokasi_id;
        }

        $fileName = 'Laporan Service Summary (Parts) - ' . $startDate . ' sampai ' . $endDate . '.xlsx';

        return Excel::download(new ServiceSummaryExport($startDate, $endDate, $invoiceNo, $lokasiId), $fileName);
    }

    // ... (salesPurchaseAnalysis tetap sama) ...
    public function salesPurchaseAnalysis(Request $request)
    {
        return view('admin.home'); // Placeholder
    }
}