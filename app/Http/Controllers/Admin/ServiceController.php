<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Service;
use App\Models\ServiceDetail; 
use App\Models\Barang;        
use App\Models\InventoryBatch; 
use App\Models\StockMovement;  
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
    public function index(Request $request)
    {
        // [MODIFIKASI] Gate Poin 16: view-service
        $this->authorize('view-service');

        $user = Auth::user();
        $query = Service::query();
        $dealers = collect();

        // --- LOGIKA STICKY FILTER (Simpan Filter ke Session) ---
        if ($request->filled('start_date') || $request->filled('end_date')) {
            session([
                'service.start_date' => $request->input('start_date'),
                'service.end_date' => $request->input('end_date'),
            ]);
        }
        
        if ($request->has('dealer_code')) {
            session(['service.dealer_code' => $request->input('dealer_code')]);
        }

        // Ambil Data (Prioritas: Request -> Session -> Default Hari Ini)
        $startDate = $request->input('start_date', session('service.start_date', now()->toDateString()));
        $endDate = $request->input('end_date', session('service.end_date', now()->toDateString()));
        // -------------------------------------------------------

        // [MODIFIKASI] Logika Filter Dealer sesuai Poin 16
        // SA, PIC, ASD, ACC -> Bisa lihat semua / filter
        // PC, KC, KSR -> Terkunci di dealer sendiri
        $canFilterByDealer = $user->isGlobal() || ($user->isPusat() && $user->hasRole(['ASD', 'ACC']));
        
        $selectedDealer = null;

        if ($canFilterByDealer) {
            $dealers = Lokasi::where('tipe', 'DEALER')->orderBy('kode_lokasi')->get(['kode_lokasi', 'nama_lokasi']);
            $selectedDealer = $request->input('dealer_code', session('service.dealer_code'));

            if ($selectedDealer && $selectedDealer !== 'all') {
                $query->where('dealer_code', $selectedDealer);
            }
        } else {
            // [MODIFIKASI] User Dealer (PC, KC, KSR) -> Lock ke kode lokasi user
            if ($user->lokasi && $user->lokasi->kode_lokasi) {
                $query->where('dealer_code', $user->lokasi->kode_lokasi);
                $selectedDealer = $user->lokasi->kode_lokasi;
            } else {
                // Safety: Jika user tidak punya lokasi, jangan tampilkan data
                $query->whereRaw('1 = 0');
            }
        }

        // KEMBALI KE FILTER TANGGAL IMPORT (created_at)
        if ($startDate && $endDate) {
            try {
                // Karena created_at ada jam-nya, kita harus set startOfDay dan endOfDay
                $start = Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
                $end = Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay();
                
                $query->whereBetween('services.created_at', [$start, $end]);

            } catch (\Exception $e) {
                // Fallback jika error
                $query->whereDate('services.created_at', today());
            }
        }

        // Sorting default kembali ke created_at
        $services = $query->orderBy('created_at', 'desc')
                          ->paginate(1000)
                          ->withQueryString();

        return view('admin.services.index', [
            'services' => $services,
            'listDealer' => $dealers,
            'selectedDealer' => $selectedDealer,
            'canFilterByDealer' => $canFilterByDealer,
            'startDate' => $startDate,
            'endDate' => $endDate
        ]);
    }

    public function import(Request $request)
    {
        // [MODIFIKASI] Gate Poin 16: manage-service (Hanya PC/KSR Dealer)
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
            $updatedCount = $import->getUpdatedCount();
            $skippedCount = $import->getSkippedCount();
            $skippedDuplicate = $import->getSkippedDuplicateCount();
            $errors = $import->getErrorMessages();

            if ($importedCount > 0 || $updatedCount > 0) {
                $message = "Sukses! {$importedCount} data baru ditambahkan.";
                if ($updatedCount > 0) $message .= " {$updatedCount} data KSG diperbarui.";
                if ($skippedDuplicate > 0) $message .= " {$skippedDuplicate} data duplikat dilewati.";
                
                // Jika ada error spesifik, tampilkan di session flash
                if (!empty($errors)) {
                    return redirect()->back()->with('success', $message)->with('import_errors', $errors);
                }
                
                return redirect()->back()->with('success', $message);
            }
            
            return redirect()->back()->with('error', 'Tidak ada data baru yang diimpor. ' . ($skippedDuplicate > 0 ? "{$skippedDuplicate} data duplikat ditemukan." : ""));

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan fatal saat membaca file: ' . $e->getMessage());
        }
    }

    public function exportExcel(Request $request)
    {
        // [MODIFIKASI] Gate Export
        $this->authorize('export-service-report');
        
        $user = Auth::user();
        
        // Ambil parameter filter
        $startDate = $request->input('start_date') ?? session('service.start_date') ?? now()->toDateString();
        $endDate = $request->input('end_date') ?? session('service.end_date') ?? now()->toDateString();
        $selectedDealer = $request->input('dealer_code') ?? session('service.dealer_code');

        // [MODIFIKASI] Logika Filter Dealer Sama dengan Index
        $canFilterByDealer = $user->isGlobal() || ($user->isPusat() && $user->hasRole(['ASD', 'ACC']));
        
        if (!$canFilterByDealer) {
            // User biasa: paksa gunakan dealer mereka sendiri
            if ($user->lokasi && $user->lokasi->kode_lokasi) {
                $selectedDealer = $user->lokasi->kode_lokasi;
            } else {
                return redirect()->back()->with('error', 'Akun Anda tidak memiliki dealer yang terkait.');
            }
        } else {
            // Super Admin/PIC: Jika tidak pilih dealer, default ke 'all'
            if (empty($selectedDealer)) {
                $selectedDealer = 'all';
            }
        }

        // Validasi format tanggal
        try {
            $validStartDate = Carbon::createFromFormat('Y-m-d', $startDate)->format('Y-m-d');
            $validEndDate = Carbon::createFromFormat('Y-m-d', $endDate)->format('Y-m-d');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Format tanggal export tidak valid.');
        }

        return Excel::download(
            new ServiceDailyReportExport($selectedDealer, $validStartDate, $validEndDate), 
            'Laporan_Service_' . $selectedDealer . '_' . $validStartDate . '.xlsx'
        );
    }

    public function show(Service $service)
    {
        // [MODIFIKASI] Gate View Service
        $this->authorize('view-service');
        
        $user = Auth::user();
        
        // [MODIFIKASI] Cek Kepemilikan Data (Jika Dealer)
        // Jika bukan Global/Pusat, pastikan dealer_code cocok
        $isGlobalOrPusat = $user->isGlobal() || ($user->isPusat() && $user->hasRole(['ASD', 'ACC']));

        if (!$isGlobalOrPusat) {
            if (!$user->lokasi || $service->dealer_code !== $user->lokasi->kode_lokasi) {
                abort(403, 'Anda tidak diizinkan melihat detail service ini.');
            }
        }

        $service->load('details.barang', 'lokasi'); 
        return view('admin.services.show', compact('service'));
    }

    public function downloadPDF($id)
    {
        // [MODIFIKASI] Gate View Service
        $this->authorize('view-service');
        
        $service = Service::with('details.barang', 'lokasi')->findOrFail($id); 
        $user = Auth::user();
        
        // [MODIFIKASI] Cek Kepemilikan Data (Sama seperti show)
        $isGlobalOrPusat = $user->isGlobal() || ($user->isPusat() && $user->hasRole(['ASD', 'ACC']));

        if (!$isGlobalOrPusat) {
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

    public function update(Request $request, Service $service)
    {
        // [MODIFIKASI] Gate Manage (Update Stok) - Hanya PC
        $this->authorize('manage-service');

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.part_id' => 'required|exists:barangs,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
        ]);

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

                $stokTersedia = InventoryBatch::where('barang_id', $barang->id)
                    ->where('lokasi_id', $lokasiId)
                    ->sum('quantity');

                if ($stokTersedia < $qtyKeluar) {
                    throw new \Exception("Stok untuk {$barang->part_name} tidak mencukupi. Tersedia: {$stokTersedia}");
                }

                $batches = InventoryBatch::where('barang_id', $barang->id)
                    ->where('lokasi_id', $lokasiId)
                    ->where('quantity', '>', 0)
                    ->orderBy('created_at', 'asc')
                    ->lockForUpdate()
                    ->get();

                $sisaQty = $qtyKeluar;
                $totalHpp = 0;

                foreach ($batches as $batch) {
                    if ($sisaQty <= 0) break;

                    $potong = min($batch->quantity, $sisaQty);
                    $costPerUnit = $barang->selling_out; // Asumsi harga modal dealer
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
                    'item_code'     => $barang->part_code,
                    'item_name'     => $barang->part_name,
                    'item_category' => 'PART',
                    'quantity'      => $qtyKeluar,
                    'price'         => $hargaJual,
                    'cost_price'    => $avgCostPrice,
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