<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Service;
use App\Imports\ServiceImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Auth;
use PDF;
use App\Models\Part;
use App\Models\InventoryBatch;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('view-service');
        $services = Service::latest()->paginate(25);

        return view('admin.services.index', compact('services'));
    }

    public function import(Request $request)
    {
        $this->authorize('manage-service');
        $request->validate([
            'file' => 'required|mimes:xls,xlsx,csv'
        ]);

        try {
            $import = new ServiceImport;
            Excel::import($import, $request->file('file'));

            $importedCount = $import->getImportedCount();
            $skippedCount = $import->getSkippedCount();

            if ($importedCount > 0) {
                $message = "Data service berhasil diimpor! {$importedCount} data baru ditambahkan.";
                if ($skippedCount > 0) {
                    $message .= " {$skippedCount} data duplikat dilewati.";
                }
                return redirect()->back()->with('success', $message);
            } elseif ($skippedCount > 0) {
                return redirect()->back()->with('error', "Impor gagal. Semua data ({$skippedCount} baris) merupakan duplikat dari data yang sudah ada.");
            } else {
                return redirect()->back()->with('error', 'Tidak ada data yang ditemukan di dalam file.');
            }

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan saat mengimpor file: ' . $e->getMessage());
        }
    }

    public function show(Service $service)
    {
        $this->authorize('view-service');
        // Tambahkan 'lokasi' di sini juga untuk konsistensi
        $service->load('details', 'lokasi');
        return view('admin.services.show', compact('service'));
    }

    /**
     * Menampilkan form untuk mengedit data service.
     */
    public function edit(Service $service)
    {
        $this->authorize('manage-service');
        
        // PERBAIKAN: Eager load relasi 'lokasi' yang baru
        $service->load('details.part', 'lokasi');

        // Tambahan: Kirim juga daftar part untuk pencarian
        $parts = Part::where('is_active', true)->orderBy('nama_part')->get();

        return view('admin.services.edit', compact('service', 'parts'));
    }

    /**
     * Memperbarui data service, khususnya menambah part baru.
     */
    public function update(Request $request, Service $service)
    {
        $this->authorize('manage-service');

        $validated = $request->validate([
            'items' => 'sometimes|array', // 'sometimes' agar tidak error jika hanya edit data lain
            'items.*.part_id' => 'required|exists:parts,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        try {
            DB::transaction(function () use ($validated, $service) {
                
                // Cari lokasi berdasarkan service, bukan user yang login
                if (!$service->lokasi) {
                    throw new \Exception("Data service ini tidak terhubung dengan lokasi dealer yang valid (dealer_code: {$service->dealer_code}).");
                }
                $lokasiId = $service->lokasi->id;
                
                if (isset($validated['items'])) {
                    foreach ($validated['items'] as $item) {
                        $part = Part::find($item['part_id']);
                        $quantity = (int)$item['quantity'];

                        // 1. Kurangi stok dari inventaris (FIFO)
                        $remainingQtyToReduce = $quantity;
                        $batches = InventoryBatch::where('part_id', $part->id)
                            ->where('gudang_id', $lokasiId)
                            ->where('quantity', '>', 0)
                            ->orderBy('created_at', 'asc')
                            ->get();

                        if ($batches->sum('quantity') < $remainingQtyToReduce) {
                            throw new \Exception("Stok untuk part '{$part->nama_part}' tidak mencukupi di lokasi service.");
                        }

                        foreach ($batches as $batch) {
                            if ($remainingQtyToReduce <= 0) break;
                            $qtyToTake = min($batch->quantity, $remainingQtyToReduce);
                            $stokTotalSebelum = InventoryBatch::where('part_id', $part->id)->where('gudang_id', $lokasiId)->sum('quantity');
                            $batch->decrement('quantity', $qtyToTake);
                            $remainingQtyToReduce -= $qtyToTake;

                            // 2. Catat pergerakan stok
                            StockMovement::create([
                                'part_id' => $part->id, 'gudang_id' => $lokasiId, 'rak_id' => $batch->rak_id,
                                'jumlah' => -$qtyToTake, 'stok_sebelum' => $stokTotalSebelum, 'stok_sesudah' => $stokTotalSebelum - $qtyToTake,
                                'referensi_type' => get_class($service), 'referensi_id' => $service->id,
                                'keterangan' => 'Penambahan part pada Service #' . $service->invoice_no,
                                'user_id' => Auth::id(),
                            ]);
                        }

                        // 3. Tambahkan item ke service_details
                        $service->details()->create([
                            'item_category' => 'PART', 'item_code' => $part->kode_part, 'item_name' => $part->nama_part,
                            'quantity' => $quantity, 'price' => $item['price'],
                        ]);
                    }
                }

                // 4. Hitung ulang total pada tabel services
                $service->refresh()->load('details'); // Muat ulang detail terbaru
                $totalPartService = $service->details()->where('item_category', 'PART')->sum(DB::raw('quantity * price'));
                $totalOilService = $service->details()->where('item_category', 'OLI')->sum(DB::raw('quantity * price'));

                $service->total_part_service = $totalPartService;
                $service->total_oil_service = $totalOilService;
                
                $totalAmount = $service->total_labor + $totalPartService + $totalOilService + $service->total_retail_parts + $service->total_retail_oil;
                $service->total_amount = $totalAmount;
                $service->total_payment = $totalAmount - $service->benefit_amount;
                $service->balance = $service->total_payment - ($service->e_payment_amount + $service->cash_amount + $service->debit_amount);
                
                $service->save();
            });

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal memperbarui service: ' . $e->getMessage())->withInput();
        }
        
        return redirect()->route('admin.services.show', $service)->with('success', 'Part baru berhasil ditambahkan ke data service.');
    }
    public function downloadPDF($id)
    {
        $this->authorize('view-service');
        $service = Service::with('details')->findOrFail($id); // Diubah dari DailyReport
        $fileName = 'Invoice-' . $service->invoice_no . '.pdf';

        $width = 24 * 28.3465;
        $height = 14 * 28.3465;
        $customPaper = [0, 0, $height, $width];

        // Nama variabel diubah menjadi 'service'
        $pdf = PDF::loadView('admin.services.pdf', compact('service'))
            ->setPaper($customPaper)
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'dpi' => 150,
                'margin-top' => 0,
                'margin-right' => 0,
                'margin-bottom' => 0,
                'margin-left' => 0,
                'defaultPaperSize' => 'custom',
            ]);

        return $pdf->stream($fileName);
    }
}
