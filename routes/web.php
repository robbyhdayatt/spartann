<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\LokasiController;
use App\Http\Controllers\Admin\RakController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\KonsumenController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\PartController;
use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Http\Controllers\Admin\ReceivingController;
use App\Http\Controllers\Admin\QcController;
use App\Http\Controllers\Admin\PutawayController;
use App\Http\Controllers\Admin\StockAdjustmentController;
use App\Http\Controllers\Admin\StockMutationController;
use App\Http\Controllers\Admin\PenjualanController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\PurchaseReturnController;
use App\Http\Controllers\Admin\SalesReturnController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\IncentiveController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Admin\QuarantineStockController;
use App\Http\Controllers\Admin\MutationReceivingController;
use App\Http\Controllers\Admin\CustomerDiscountCategoryController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\DealerController;
use App\Http\Controllers\Admin\PdfController;
use App\Http\Controllers\Admin\ConvertController;
use App\Http\Controllers\Admin\BarangController;

Route::get('/', fn() => redirect()->route('login'));

Auth::routes();

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {

    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');

    // === MASTER DATA & PENGATURAN ===
    Route::resource('brands', BrandController::class);
    Route::resource('categories', CategoryController::class);
    Route::resource('lokasi', LokasiController::class)->except(['show']);
    Route::resource('dealers', DealerController::class)->except(['show']);
    Route::resource('raks', RakController::class);
    Route::resource('suppliers', SupplierController::class);
    Route::resource('konsumens', KonsumenController::class);
    Route::resource('users', UserController::class);
    Route::get('parts/search', [PartController::class, 'search'])->name('parts.search');
    Route::resource('parts', PartController::class);
    Route::post('parts/import', [PartController::class, 'import'])->name('parts.import');
    Route::resource('barangs', BarangController::class)->except(['create', 'edit']);

    // === TRANSAKSI GUDANG & DEALER ===
    Route::resource('purchase-orders', PurchaseOrderController::class)->except(['edit', 'update', 'destroy']);
    Route::post('purchase-orders/{purchase_order}/approve', [PurchaseOrderController::class, 'approve'])->name('purchase-orders.approve');
    Route::post('purchase-orders/{purchase_order}/reject', [PurchaseOrderController::class, 'reject'])->name('purchase-orders.reject');
    Route::get('purchase-orders/{purchaseOrder}/pdf', [PdfController::class, 'purchaseOrder'])->name('purchase-orders.pdf');

    Route::resource('receivings', ReceivingController::class)->only(['index', 'create', 'store', 'show']);

    Route::get('quality-control', [QcController::class, 'index'])->name('qc.index');
    Route::get('quality-control/{receiving}/form', [QcController::class, 'showQcForm'])->name('qc.form');
    Route::post('quality-control/{receiving}', [QcController::class, 'storeQcResult'])->name('qc.store');

    Route::get('putaway', [PutawayController::class, 'index'])->name('putaway.index');
    Route::get('putaway/{receiving}/form', [PutawayController::class, 'showPutawayForm'])->name('putaway.form');
    Route::post('putaway/{receiving}', [PutawayController::class, 'storePutaway'])->name('putaway.store');

    Route::resource('stock-adjustments', StockAdjustmentController::class)->except(['show', 'edit', 'update', 'destroy']);
    Route::post('stock-adjustments/{stockAdjustment}/approve', [StockAdjustmentController::class, 'approve'])->name('stock-adjustments.approve');
    Route::post('stock-adjustments/{stockAdjustment}/reject', [StockAdjustmentController::class, 'reject'])->name('stock-adjustments.reject');

    Route::resource('stock-mutations', StockMutationController::class)->except(['edit', 'update', 'destroy'])->names('stock-mutations');
    Route::post('stock-mutations/{stock_mutation}/approve', [StockMutationController::class, 'approve'])->name('stock-mutations.approve');
    Route::post('stock-mutations/{stock_mutation}/reject', [StockMutationController::class, 'reject'])->name('stock-mutations.reject');

    Route::resource('purchase-returns', PurchaseReturnController::class)->only(['index', 'create', 'store', 'show']);
    Route::resource('sales-returns', SalesReturnController::class);

    Route::get('mutation-receiving', [MutationReceivingController::class, 'index'])->name('mutation-receiving.index');
    Route::get('mutation-receiving/{mutation}', [MutationReceivingController::class, 'show'])->name('mutation-receiving.show');
    Route::post('mutation-receiving/{mutation}', [MutationReceivingController::class, 'receive'])->name('mutation-receiving.receive');

    Route::get('quarantine-stock', [QuarantineStockController::class, 'index'])->name('quarantine-stock.index');
    Route::post('quarantine-stock/process', [QuarantineStockController::class, 'process'])->name('quarantine-stock.process');

    // === PENJUALAN & MARKETING ===
    Route::resource('campaigns', CampaignController::class);
    Route::resource('customer-discount-categories', CustomerDiscountCategoryController::class);
    Route::resource('penjualans', PenjualanController::class)->except(['edit', 'update', 'destroy']);
    Route::get('penjualans/{penjualan}/print', [PenjualanController::class, 'print'])->name('penjualans.print');
    Route::get('penjualans/{penjualan}/pdf', [PdfController::class, 'penjualan'])->name('penjualans.pdf');


    Route::get('incentives/targets', [IncentiveController::class, 'targets'])->name('incentives.targets');
    Route::post('incentives/targets', [IncentiveController::class, 'storeTarget'])->name('incentives.targets.store');
    Route::get('incentives/report', [IncentiveController::class, 'report'])->name('incentives.report');
    Route::post('incentives/{incentive}/mark-as-paid', [IncentiveController::class, 'markAsPaid'])->name('incentives.mark-as-paid');

    // === SERVICE ===
    Route::get('services', [ServiceController::class, 'index'])->name('services.index');
    Route::post('services/import', [ServiceController::class, 'import'])->name('services.import');
    // Pindahkan Export Excel SEBELUM route dengan parameter
    Route::get('services/export-excel', [App\Http\Controllers\Admin\ServiceController::class, 'exportExcel'])->name('services.export.excel');
    // Route dengan parameter setelahnya
    Route::get('services/{service}', [ServiceController::class, 'show'])->name('services.show');
    Route::get('services/{id}/pdf', [ServiceController::class, 'downloadPDF'])->name('services.pdf');

    // === LAPORAN ===
    Route::get('reports/stock-card', [ReportController::class, 'stockCard'])->name('reports.stock-card');
    Route::get('reports/stock-by-warehouse', [ReportController::class, 'stockByWarehouse'])->name('reports.stock-by-warehouse');
    Route::get('reports/stock-report', [ReportController::class, 'stockReport'])->name('reports.stock-report');
    Route::get('reports/sales-journal', [ReportController::class, 'salesJournal'])->name('reports.sales-journal');
    Route::get('reports/sales-journal/export', [ReportController::class, 'exportSalesJournal'])->name('reports.sales-journal.export');
    Route::get('reports/purchase-journal', [ReportController::class, 'purchaseJournal'])->name('reports.purchase-journal');
    Route::get('reports/inventory-value', [ReportController::class, 'inventoryValue'])->name('reports.inventory-value');
    Route::get('reports/rekomendasi-po', [ReportController::class, 'rekomendasiPo'])->name('reports.rekomendasi-po');
    Route::get('reports/stock-card/export', [ReportController::class, 'exportStockCard'])->name('reports.stock-card.export');
    Route::get('reports/purchase-journal/export', [ReportController::class, 'exportPurchaseJournal'])->name('reports.purchase-journal.export');
    Route::get('reports/inventory-value/export', [ReportController::class, 'exportInventoryValue'])->name('reports.inventory-value.export');
    Route::get('reports/stock-by-warehouse/export', [ReportController::class, 'exportStockByWarehouse'])->name('reports.stock-by-warehouse.export');
    Route::get('reports/sales-summary', [ReportController::class, 'salesSummary'])->name('reports.sales-summary');
    Route::get('reports/sales-summary/export', [ReportController::class, 'exportSalesSummary'])->name('reports.sales-summary.export');
    Route::get('reports/service-summary', [ReportController::class, 'serviceSummary'])->name('reports.service-summary');
    Route::get('reports/service-summary/export', [ReportController::class, 'exportServiceSummary'])->name('reports.service-summary.export');

    // === CONVERT ===
    // Hapus: Route::resource('converts', ConvertController::class)->except(['show']);

    // ++ TAMBAHKAN ROUTE MANUAL UNTUK CONVERTS ++
    Route::get('converts', [ConvertController::class, 'index'])->name('converts.index');
    Route::post('converts', [ConvertController::class, 'store'])->name('converts.store');
    // Rute 'editData' (Show) menggunakan route model binding ke model Convert (VIEW)
    Route::get('converts/{convert}/data', [ConvertController::class, 'getEditData'])->name('converts.editData');
    // Rute Update dan Destroy menggunakan {id} (bukan {convert}) agar tidak binding ke VIEW
    Route::put('converts/{id}', [ConvertController::class, 'update'])->name('converts.update');
    Route::delete('converts/{id}', [ConvertController::class, 'destroy'])->name('converts.destroy');
    // ++ AKHIR ROUTE CONVERTS ++

        // === API (untuk AJAX) ===
        Route::get('/api/purchase-orders/{purchaseOrder}/details', [ReceivingController::class, 'getPurchaseOrderDetails'])->name('api.po.details');
        Route::get('api/lokasi/{lokasi}/parts-with-stock', [StockMutationController::class, 'getPartsWithStock'])->name('api.lokasi.parts-with-stock');
        // Route::get('/api/lokasi/{lokasi}/parts', [PenjualanController::class, 'getPartsByLokasi'])->name('api.lokasi.parts');
        Route::get('api/parts/{part}/stock', [PenjualanController::class, 'getPartStockDetails'])->name('api.part.stock');
        Route::get('/api/lokasi/{lokasi}/raks', [StockAdjustmentController::class, 'getRaksByLokasi'])->name('api.lokasi.raks');
        Route::get('api/lokasi/{lokasi}/adjustment-raks', [StockAdjustmentController::class, 'getRaksByGudang'])->name('api.gudang.raks.for.adjustment');
        Route::get('/api/receivings/{receiving}/failed-items', [PurchaseReturnController::class, 'getFailedItems'])->name('api.receivings.failed-items');
        Route::get('/api/penjualans/{penjualan}/returnable-items', [SalesReturnController::class, 'getReturnableItems'])->name('penjualans.returnable-items');
        Route::get('api/parts/{part}/purchase-details', [PurchaseOrderController::class, 'getPartPurchaseDetails'])->name('api.part.purchase-details');
        Route::get('api/part-stock-details', [StockMutationController::class, 'getPartStockDetails'])->name('api.part.stock-details');
        Route::get('api/calculate-discount', [PenjualanController::class, 'calculateDiscount'])->name('api.calculate-discount');
        // Route::get('api/get-fifo-batches', [PenjualanController::class, 'getFifoBatches'])->name('api.get-fifo-batches');
        Route::get('/parts/search', [PartController::class, 'search'])->name('parts.search');
        Route::get('api/get-barang-items', [PenjualanController::class, 'getBarangItems'])->name('api.get-barang-items');
    });

