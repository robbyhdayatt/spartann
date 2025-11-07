<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\Lokasi;
use App\Models\InventoryBatch; // <--- UBAH DARI Inventory
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\StockByWarehouseExport;
use App\Exports\SalesJournalExport;
use App\Models\PenjualanDetail;
use App\Exports\PurchaseJournalExport;
use App\Models\ReceivingDetail;
use App\Exports\InventoryValueExport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Exports\StockCardExport;


class ReportController extends Controller
{
public function stockCard(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $movements = collect(); // Koleksi kosong by default
        $lokasis = collect(); // Koleksi lokasi kosong by default
        $selectedLokasiId = $request->input('lokasi_id'); // Ambil dari request

        // Cek jika user BUKAN admin/manajer (yaitu, staf lokasi seperti KG atau KC)
        if (!$user->hasRole(['SA', 'PIC', 'MA'])) {
            // Jika ya, paksa pilihan lokasi hanya lokasinya sendiri
            if ($user->lokasi_id) {
                $lokasis = Lokasi::where('id', $user->lokasi_id)->get();
                // Paksa ID lokasi filter ke lokasi user, abaikan input request
                $selectedLokasiId = $user->lokasi_id;
            }
            // Jika mereka tidak punya lokasi_id, $lokasis akan tetap kosong
        } else {
            // Jika SA/PIC/MA, tampilkan semua lokasi
            $lokasis = Lokasi::where('is_active', true)->orderBy('nama_lokasi')->get();
        }

        // Ambil semua part untuk pilihan dropdown
        $parts = Part::where('is_active', true)->orderBy('nama_part')->get();

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        // Hanya jalankan query jika part_id diisi
        if ($request->filled('part_id')) {
            $query = StockMovement::where('part_id', $request->part_id)
                ->with(['lokasi', 'user']) // Eager load
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate);

            // Terapkan filter lokasi
            if ($selectedLokasiId) {
                $query->where('lokasi_id', $selectedLokasiId);
            }
            // Jika SA/PIC/MA dan tidak memilih lokasi, mereka melihat semua (tidak ada filter lokasi)

            $movements = $query->oldest()->get();
        }

        // Kirim $selectedLokasiId ke view untuk menandai <option> yang dipilih
        return view('admin.reports.stock_card', compact('parts', 'lokasis', 'movements', 'startDate', 'endDate', 'selectedLokasiId'));
    }

public function stockByWarehouse(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $inventoryItems = collect();

        // ++ PERBAIKAN: Ubah nama variabel $lokasis menjadi $lokasiList ++
        $lokasiList = collect();
        // ++ PERBAIKAN: Ubah nama variabel $selectedlokasi menjadi $selectedLokasi (L besar) ++
        $selectedLokasi = null;

        // ++ PERBAIKAN: Gunakan hasRole untuk cek jabatan KG atau KC ++
        // (Asumsi 'Kepala lokasi' di database Anda sesuai dengan role 'KG' atau 'KC')
        if ($user->hasRole(['KG', 'KC'])) {
            // Jika user adalah Kepala Gudang/Cabang, hanya tampilkan lokasi mereka
            $lokasiList = Lokasi::where('id', $user->lokasi_id)->get();
            // Langsung set request agar data mereka tampil
            if (!$request->filled('lokasi_id')) {
                $request->merge(['lokasi_id' => $user->lokasi_id]);
            }
        } else {
            // Jika SA, PIC, MA, dll., tampilkan semua lokasi
            $lokasiList = Lokasi::where('is_active', true)->orderBy('nama_lokasi')->get();
        }

        if ($request->filled('lokasi_id')) {
            // ++ PERBAIKAN: Pastikan konsisten menggunakan $selectedLokasi (L besar) ++
            $selectedLokasi = Lokasi::find($request->lokasi_id);

            $inventoryItems = InventoryBatch::select(
                    'part_id',
                    'rak_id',
                    'lokasi_id',
                    DB::raw('SUM(quantity) as quantity')
                )
                ->where('lokasi_id', $request->lokasi_id)
                ->where('quantity', '>', 0)
                ->with([
                    'part:id,kode_part,nama_part,satuan,brand_id,category_id',
                    'part.brand:id,nama_brand',
                    'part.category:id,nama_kategori',
                    'rak:id,kode_rak',
                    'lokasi:id,nama_lokasi,kode_lokasi' // Tambahkan relasi lokasi
                ])
                ->groupBy('part_id', 'rak_id', 'lokasi_id')
                ->get()
                ->sortBy('part.nama_part');
        }

        // ++ PERBAIKAN: Sesuaikan nama variabel di compact() agar cocok dengan view ++
        return view('admin.reports.stock_by_warehouse', compact(
            'inventoryItems',
            'lokasiList',     // Kirim sebagai 'lokasiList'
            'selectedLokasi'  // Kirim sebagai 'selectedLokasi'
        ));
    }

    public function exportStockByWarehouse(Request $request)
    {
        $request->validate([
            'lokasi_id' => 'required|exists:lokasis,id'
        ]);

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

        // Mulai query dasar
        $query = PenjualanDetail::with(['penjualan.konsumen', 'penjualan.sales', 'barang'])
            ->whereHas('penjualan', function ($q) use ($startDate, $endDate, $user) {
                $q->whereBetween('tanggal_jual', [$startDate, $endDate]);

                // ++ TAMBAHKAN FILTER LOKASI INI ++
                // Jika user bukan Manajer ke atas (artinya KG/KC) dan punya lokasi_id
                if (!$user->hasRole(['SA', 'PIC', 'MA']) && $user->lokasi_id) {
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

        // Mulai query dasar
        $query = ReceivingDetail::with(['receiving.purchaseOrder.supplier', 'part'])
            ->whereHas('receiving', function ($q) use ($startDate, $endDate, $user) {
                $q->whereBetween('tanggal_terima', [$startDate, $endDate]);

                // ++ TAMBAHKAN FILTER LOKASI INI ++
                // (Relasi: ReceivingDetail -> Receiving -> lokasi_id)
                if (!$user->hasRole(['SA', 'PIC', 'MA']) && $user->lokasi_id) {
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
                'part_id',
                'lokasi_id',
                'rak_id',
                DB::raw('SUM(quantity) as quantity')
            )
            ->where('quantity', '>', 0)
            ->with(['part', 'lokasi', 'rak'])
            ->groupBy('part_id', 'lokasi_id', 'rak_id');

        if (!$user->hasRole(['SA', 'PIC', 'MA']) && $user->lokasi_id) {
            $inventoryQuery->where('lokasi_id', $user->lokasi_id);
        }

        $inventoryDetails = $inventoryQuery->get();

        $totalValue = $inventoryDetails->sum(function($item) {
            if ($item->part) {
                return $item->quantity * $item->part->harga_satuan;
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

        // Top 10 Selling Parts (by quantity)
        $topSellingParts = \App\Models\PenjualanDetail::select('part_id', DB::raw('SUM(qty_jual) as total_qty'))
            ->with('part')
            ->whereHas('penjualan', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('tanggal_jual', [$startDate, $endDate]);
            })
            ->groupBy('part_id')
            ->orderBy('total_qty', 'desc')
            ->limit(10)
            ->get();

        // Top 10 Purchased Parts (by quantity)
        $topPurchasedParts = \App\Models\ReceivingDetail::select('part_id', DB::raw('SUM(qty_terima) as total_qty'))
            ->with('part')
            ->whereHas('receiving', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('tanggal_terima', [$startDate, $endDate]);
            })
            ->groupBy('part_id')
            ->orderBy('total_qty', 'desc')
            ->limit(10)
            ->get();

        // Sales by Category (for chart)
        $salesByCategory = \App\Models\PenjualanDetail::join('parts', 'penjualan_details.part_id', '=', 'parts.id')
            ->join('categories', 'parts.category_id', '=', 'categories.id')
            ->join('penjualans', 'penjualan_details.penjualan_id', '=', 'penjualans.id')
            ->whereBetween('penjualans.tanggal_jual', [$startDate, $endDate])
            ->groupBy('categories.nama_kategori')
            ->select('categories.nama_kategori', DB::raw('SUM(penjualan_details.subtotal) as total_value'))
            ->pluck('total_value', 'nama_kategori');

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
        // Mengambil data dari inventory_batches, menjumlahkan, dan mengelompokkan
        $inventoryDetails = InventoryBatch::select(
                'part_id',
                'lokasi_id',
                'rak_id',
                DB::raw('SUM(quantity) as quantity')
            )
            ->where('quantity', '>', 0)
            ->with(['part', 'lokasi', 'rak'])
            ->groupBy('part_id', 'lokasi_id', 'rak_id')
            ->get()
            ->sortBy(['part.nama_part', 'lokasi.nama_lokasi']);

        return view('admin.reports.stock_report', compact('inventoryDetails'));
    }

    public function exportStockCard(Request $request)
    {
        $request->validate([
            'part_id' => 'required|exists:parts,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'lokasi_id' => 'nullable|exists:lokasis,id'
        ]);

        $part = Part::findOrFail($request->part_id);
        $fileName = 'Kartu Stok - ' . $part->kode_part . ' - ' . $request->start_date . ' sampai ' . $request->end_date . '.xlsx';

        return Excel::download(new StockCardExport(
            $request->part_id,
            $request->lokasi_id,
            $request->start_date,
            $request->end_date
        ), $fileName);
    }

    public function rekomendasiPo()
    {
        return view('admin.reports.rekomendasi_po');
    }

    public function salesSummary(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        // Query utama untuk ringkasan penjualan
        $query = DB::table('penjualan_details')
            ->join('penjualans', 'penjualan_details.penjualan_id', '=', 'penjualans.id')
            ->join('barangs', 'penjualan_details.barang_id', '=', 'barangs.id')
            ->select(
                'barangs.part_code',
                'barangs.part_name',
                DB::raw('SUM(penjualan_details.qty_jual) as total_qty'),
                DB::raw('SUM(penjualan_details.subtotal) as total_penjualan'),
                DB::raw('SUM(penjualan_details.qty_jual * barangs.harga_modal) as total_modal'),
                DB::raw('SUM(penjualan_details.subtotal) - SUM(penjualan_details.qty_jual * barangs.harga_modal) as total_keuntungan')
            )
            ->whereBetween('penjualans.tanggal_jual', [$startDate, $endDate])
            ->groupBy('penjualan_details.barang_id', 'barangs.part_code', 'barangs.part_name')
            ->orderBy('total_qty', 'desc');

        // Filter lokasi untuk user non-admin
        if (!$user->hasRole(['SA', 'PIC', 'MA']) && $user->lokasi_id) {
            $query->where('penjualans.lokasi_id', $user->lokasi_id);
        }

        $reportData = $query->get();

        // Hitung Grand Total
        $grandTotalQty = $reportData->sum('total_qty');
        $grandTotalPenjualan = $reportData->sum('total_penjualan');
        $grandTotalModal = $reportData->sum('total_modal');
        $grandTotalKeuntungan = $reportData->sum('total_keuntungan');

        return view('admin.reports.sales_summary', compact(
            'reportData',
            'grandTotalQty',
            'grandTotalPenjualan',
            'grandTotalModal',
            'grandTotalKeuntungan',
            'startDate',
            'endDate'
        ));
    }

    public function exportSalesSummary(Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $fileName = 'Laporan Penjualan - ' . $startDate . ' sampai ' . $endDate . '.xlsx';

        // Kita akan buat file App\Exports\SalesSummaryExport di langkah berikutnya
        return Excel::download(new \App\Exports\SalesSummaryExport($startDate, $endDate), $fileName);
    }

    public function serviceSummary(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $invoiceNo = $request->input('invoice_no'); // Filter baru

        // Query utama untuk ringkasan service
        $query = DB::table('service_details')
            ->join('services', 'service_details.service_id', '=', 'services.id')
            ->leftJoin('parts', 'service_details.item_code', '=', 'parts.kode_part')
            ->select(
                'service_details.item_code',
                'service_details.item_name',
                'service_details.item_category',
                DB::raw('SUM(service_details.quantity) as total_qty'),
                DB::raw('SUM(service_details.price + service_details.labor_cost_service) as total_penjualan'),
                DB::raw("SUM(CASE
                                WHEN service_details.item_category != 'JASA' THEN service_details.quantity * parts.harga_satuan
                                ELSE 0
                            END) as total_modal"),
                DB::raw("SUM(service_details.price + service_details.labor_cost_service) -
                         SUM(CASE
                                WHEN service_details.item_category != 'JASA' THEN service_details.quantity * parts.harga_satuan
                                ELSE 0
                            END) as total_keuntungan")
            )
            ->whereBetween('services.reg_date', [$startDate, $endDate])
            ->groupBy('service_details.item_code', 'service_details.item_name', 'service_details.item_category')
            ->orderBy('total_qty', 'desc');

        // Filter lokasi untuk user non-admin
        if (!$user->hasRole(['SA', 'PIC', 'MA']) && $user->lokasi_id) {
            // Asumsi 'services.lokasi_id' ada berdasarkan model Service Anda
            $query->where('services.lokasi_id', $user->lokasi_id);
        }

        // Filter berdasarkan Nomor Invoice
        if ($invoiceNo) {
            $query->where('services.invoice_no', 'like', '%' . $invoiceNo . '%');
        }

        $reportData = $query->get();

        // Hitung Grand Total
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

        // Kita akan buat file App\Exports\ServiceSummaryExport di langkah berikutnya
        return Excel::download(new \App\Exports\ServiceSummaryExport($startDate, $endDate, $invoiceNo), $fileName);
    }
}
