<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Service;
use App\Models\ServiceDetail; // Tambahkan ini
use App\Models\Barang;        // Tambahkan ini
use App\Models\InventoryBatch; // Tambahkan ini
use App\Models\StockMovement;  // Tambahkan ini
use App\Imports\ServiceImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Auth;
use PDF;
use App\Models\Dealer;
use App\Models\Lokasi;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Exports\ServiceDailyReportExport;

class ServiceController extends Controller
{
    // ... (Method index, import, show, downloadPDF, exportExcel TETAP SAMA seperti sebelumnya) ...

    // ... Salin method index() dari file lama Anda ...
    public function index(Request $request)
    {
        $this->authorize('view-service');

        $user = Auth::user();
        $query = Service::query();
        $dealers = collect();
        $selectedDealer = null;
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $canFilterByDealer = $user->jabatan && in_array($user->jabatan->singkatan, ['SA', 'PIC', 'ASD']);

        if ($canFilterByDealer) {
            $dealers = Lokasi::where('tipe', 'DEALER')->orderBy('kode_lokasi')->get(['kode_lokasi', 'nama_lokasi']);
            $selectedDealer = $request->input('dealer_code');

            if ($selectedDealer && $selectedDealer !== 'all') {
                $query->where('dealer_code', $selectedDealer);
            }
        } else {
            if ($user->lokasi && $user->lokasi->kode_lokasi) {
                $query->where('dealer_code', $user->lokasi->kode_lokasi);
                $selectedDealer = $user->lokasi->kode_lokasi;
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($startDate && $endDate) {
            try {
                $validStartDate = Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
                $validEndDate = Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay();
                $query->whereBetween('services.created_at', [$validStartDate, $validEndDate]);

            } catch (\Exception $e) {
                $startDate = now()->startOfMonth()->toDateString();
                $endDate = now()->endOfMonth()->toDateString();
                $query->whereBetween('services.created_at', [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()]);
            }
        }

        $services = $query->orderBy('created_at', 'desc')->paginate(1000)->withQueryString();

        return view('admin.services.index', [
            'services' => $services,
            'listDealer' => $dealers,
            'selectedDealer' => $selectedDealer,
            'canFilterByDealer' => $canFilterByDealer,
            'startDate' => $startDate,
            'endDate' => $endDate
        ]);
    }

    // ... Salin method import(), show(), downloadPDF(), exportExcel() dari file lama Anda ...
    // (Pastikan Anda menyalin method-method tersebut ke sini)

    public function show(Service $service)
    {
        $user = Auth::user();
        $isSuperAdminOrPic = $user->hasRole(['SA', 'PIC']);

        if (!$isSuperAdminOrPic) {
            if (!$user->lokasi || $service->dealer_code !== $user->lokasi->kode_lokasi) {
                abort(403, 'Anda tidak diizinkan melihat detail service ini.');
            }
        }

        $this->authorize('view-service');
        $service->load('details.barang', 'lokasi'); // Updated relation load
        return view('admin.services.show', compact('service'));
    }

    public function downloadPDF($id)
    {
        $this->authorize('view-service');
        $service = Service::with('details.barang', 'lokasi')->findOrFail($id); // Updated relation

        $user = Auth::user();
        $isSuperAdminOrPic = $user->hasRole(['SA', 'PIC']);

        if (!$isSuperAdminOrPic) {
            if (!$user->lokasi || $service->dealer_code !== $user->lokasi->kode_lokasi) {
                abort(403, 'Anda tidak diizinkan mengunduh PDF service ini.');
            }
        }

        if (is_null($service->printed_at)) {
            $service->printed_at = now();
            $service->save();
        }

        $fileName = 'Invoice-' . $service->invoice_no . '.pdf';
        $width_cm = 24;
        $height_cm = 14;
        $points_per_cm = 28.3465;
        $widthInPoints = $width_cm * $points_per_cm;
        $heightInPoints = $height_cm * $points_per_cm;
        $customPaper = [0, 0, $widthInPoints, $heightInPoints];

        $pdf = PDF::loadView('admin.services.pdf', compact('service'))
            ->setPaper($customPaper)
            ->setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true]);

        return $pdf->stream($fileName);
    }

    public function import(Request $request)
    {
        $this->authorize('manage-service');
        $request->validate([
            'file' => 'required|mimes:xls,xlsx,csv'
        ]);

        try {
            $user = Auth::user();
            if (!$user->lokasi || !$user->lokasi->kode_lokasi) {
                return redirect()->back()->with('error', 'Gagal mengimpor: Akun Anda tidak terasosiasi dengan dealer manapun.');
            }
            $userDealerCode = $user->lokasi->kode_lokasi;

            $import = new ServiceImport($userDealerCode);
            Excel::import($import, $request->file('file'));

            $importedCount = $import->getImportedCount();
            $skippedCount = $import->getSkippedCount();
            $skippedDealerCount = $import->getSkippedDealerCount();

            if ($importedCount > 0) {
                $message = "Data service berhasil diimpor! {$importedCount} data baru ditambahkan.";
                if ($skippedCount > 0) {
                    $message .= " {$skippedCount} data duplikat/gagal dilewati (cek log).";
                }
                if ($skippedDealerCount > 0) {
                    $message .= " {$skippedDealerCount} data ditolak karena tidak sesuai dengan dealer Anda.";
                }
                return redirect()->back()->with('success', $message);
            }
             return redirect()->back()->with('error', 'Import gagal atau tidak ada data.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan saat mengimpor file: ' . $e->getMessage());
        }
    }

    public function exportExcel(Request $request)
    {
        $this->authorize('export-service-report');
        $user = Auth::user();
        $selectedDealer = $request->query('dealer_code');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        if (!$startDate || !$endDate) return redirect()->back()->with('error', 'Pilih tanggal.');

        try {
            $validStartDate = Carbon::createFromFormat('Y-m-d', $startDate)->format('Y-m-d');
            $validEndDate = Carbon::createFromFormat('Y-m-d', $endDate)->format('Y-m-d');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Format tanggal export tidak valid.');
        }

        return Excel::download(new ServiceDailyReportExport($selectedDealer, $validStartDate, $validEndDate), 'Laporan_Service.xlsx');
    }

    public function update(Request $request, Service $service)
    {
        $this->authorize('manage-service');

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.part_id' => 'required|exists:barangs,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        // Pastikan user punya lokasi untuk memotong stok
        $user = Auth::user();
        if (!$user->lokasi_id) {
            return back()->with('error', 'Akun Anda tidak memiliki lokasi gudang. Stok tidak dapat diproses.');
        }
        $lokasiId = $user->lokasi_id;

        DB::beginTransaction();
        try {
            foreach ($validated['items'] as $item) {
                $barang = Barang::find($item['part_id']);
                $qtyKeluar = $item['quantity'];
                $hargaJual = $item['price'];

                // 1. Cek Ketersediaan Stok
                $stokTersedia = InventoryBatch::where('barang_id', $barang->id)
                    ->where('lokasi_id', $lokasiId)
                    ->sum('quantity');

                if ($stokTersedia < $qtyKeluar) {
                    throw new \Exception("Stok untuk {$barang->part_name} tidak mencukupi. Tersedia: {$stokTersedia}");
                }

                // 2. Logika FIFO (Potong Batch)
                $batches = InventoryBatch::where('barang_id', $barang->id)
                    ->where('lokasi_id', $lokasiId)
                    ->where('quantity', '>', 0)
                    ->orderBy('created_at', 'asc')
                    ->get();

                $sisaQty = $qtyKeluar;
                $totalHpp = 0;

                foreach ($batches as $batch) {
                    if ($sisaQty <= 0) break;

                    $potong = min($batch->quantity, $sisaQty);
                    $costPerUnit = $barang->selling_out;
                    $totalHpp += ($costPerUnit * $potong);
                    $batch->decrement('quantity', $potong);

                    StockMovement::create([
                        'barang_id'      => $barang->id,
                        'lokasi_id'      => $lokasiId,
                        'rak_id'         => $batch->rak_id,
                        'jumlah'         => -$potong,
                        'stok_sebelum'   => $batch->quantity + $potong,
                        'stok_sesudah'   => $batch->quantity,
                        'referensi_type' => get_class($service),
                        'referensi_id'   => $service->id,
                        'keterangan'     => "Pemakaian Service Invoice #{$service->invoice_no}",
                        'user_id'        => $user->id,
                    ]);

                    $sisaQty -= $potong;
                }

                $avgCostPrice = ($qtyKeluar > 0) ? ($totalHpp / $qtyKeluar) : 0;

                ServiceDetail::create([
                    'service_id'    => $service->id,
                    'barang_id'     => $barang->id,
                    'item_code'     => $barang->part_code, // Historical data
                    'item_name'     => $barang->part_name,
                    'item_category' => 'PART', // Default category
                    'quantity'      => $qtyKeluar,
                    'price'         => $hargaJual,
                    'cost_price'    => $avgCostPrice, // Simpan HPP
                    'subtotal'      => $qtyKeluar * $hargaJual,
                    'package_name'  => '-',
                ]);
            }

            DB::commit();
            return redirect()->route('admin.services.edit', $service->id)->with('success', 'Part berhasil ditambahkan dan stok telah diperbarui.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menyimpan: ' . $e->getMessage());
        }
    }
}
