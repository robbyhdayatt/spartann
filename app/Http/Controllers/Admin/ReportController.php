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
        // Ambil semua part dan lokasi untuk pilihan dropdown
        $user = Auth::user();
        $lokasis = Lokasi::where('is_active', true)->orderBy('nama_lokasi')->get();
        $movements = collect(); // Buat koleksi kosong secara default

        // Cek jika user adalah Kepala lokasi
        if ($user->jabatan->nama_jabatan === 'Kepala lokasi') {
            // Jika ya, paksa pilihan lokasi hanya lokasinya sendiri
            $lokasis = Lokasi::where('id', $user->lokasi_id)->get();
        } else {
            // Jika bukan, tampilkan semua lokasi
            $lokasis = Lokasi::where('is_active', true)->orderBy('nama_lokasi')->get();
        }

        // Ambil semua part untuk pilihan dropdown
        $parts = Part::where('is_active', true)->orderBy('nama_part')->get();

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        // Jika ada part yang dipilih dari form, cari datanya
        if ($request->filled('part_id')) {
            $query = StockMovement::where('part_id', $request->part_id)
                ->with(['lokasi', 'user']) // Eager load untuk efisiensi
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate);

            // Tambahkan filter lokasi jika dipilih
            if ($request->filled('lokasi_id')) {
                $query->where('lokasi_id', $request->lokasi_id);
            }

            // Jika yang login Kepala lokasi, paksa filter lokasi
            if ($user->jabatan->nama_jabatan === 'Kepala lokasi') {
                $query->where('lokasi_id', $user->lokasi_id);
            }

            $movements = $query->oldest()->get();
        }

        return view('admin.reports.stock_card', compact('parts', 'lokasis', 'movements', 'startDate', 'endDate'));
    }

    public function stockByWarehouse(Request $request)
    {
        $user = Auth::user();
        $inventoryItems = collect();
        $lokasis = collect();
        $selectedlokasi = null;

        if ($user->jabatan->nama_jabatan === 'Kepala lokasi') {
            $lokasis = Lokasi::where('id', $user->lokasi_id)->get();
            if (!$request->filled('lokasi_id')) {
                $request->merge(['lokasi_id' => $user->lokasi_id]);
            }
        } else {
            $lokasis = Lokasi::where('is_active', true)->orderBy('nama_lokasi')->get();
        }

        if ($request->filled('lokasi_id')) {
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
                    'rak:id,kode_rak'
                ])
                ->groupBy('part_id', 'rak_id', 'lokasi_id')
                ->get()
                ->sortBy('part.nama_part');
        }

        return view('admin.reports.stock_by_warehouse', compact('inventoryItems', 'lokasis', 'selectedlokasi'));
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
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        $salesDetails = PenjualanDetail::with(['penjualan.konsumen', 'penjualan.sales', 'part'])
            ->whereHas('penjualan', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('tanggal_jual', [$startDate, $endDate]);
            })
            ->latest()
            ->get();

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
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        $purchaseDetails = ReceivingDetail::with(['receiving.purchaseOrder.supplier', 'part'])
            ->whereHas('receiving', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('tanggal_terima', [$startDate, $endDate]);
            })
            ->latest()
            ->get();

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
        // Mengambil data dari inventory_batches dan mengelompokkannya
        $inventoryDetails = InventoryBatch::select(
                'part_id',
                'lokasi_id',
                'rak_id',
                DB::raw('SUM(quantity) as quantity')
            )
            ->where('quantity', '>', 0)
            ->with(['part', 'lokasi', 'rak'])
            ->groupBy('part_id', 'lokasi_id', 'rak_id')
            ->get();

        // Menghitung total nilai persediaan
        $totalValue = $inventoryDetails->sum(function($item) {
            // Pastikan relasi part tidak null untuk menghindari error
            if ($item->part) {
                return $item->quantity * $item->part->harga_beli_rata_rata;
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
}
