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

        $user = Auth::user();
        $query = Service::query();

        // Batasi data berdasarkan dealer user, kecuali untuk Superadmin
        if ($user->jabatan && $user->jabatan->nama_jabatan !== 'Superadmin') {
            if ($user->lokasi && $user->lokasi->kode_gudang) {
                $query->where('dealer_code', $user->lokasi->kode_gudang);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // Ambil data dengan paginasi
        $services = $query->latest()->paginate(25);

        return view('admin.services.index', compact('services'));
    }

    public function import(Request $request)
    {
        $this->authorize('manage-service');
        $request->validate([
            'file' => 'required|mimes:xls,xlsx,csv'
        ]);

        try {
            $user = Auth::user();
            if (!$user->lokasi || !$user->lokasi->kode_gudang) {
                return redirect()->back()->with('error', 'Gagal mengimpor: Akun Anda tidak terasosiasi dengan dealer manapun.');
            }
            $userDealerCode = $user->lokasi->kode_gudang;

            $import = new ServiceImport($userDealerCode);
            Excel::import($import, $request->file('file'));

            $importedCount = $import->getImportedCount();
            $skippedCount = $import->getSkippedCount();
            $skippedDealerCount = $import->getSkippedDealerCount();

            if ($importedCount > 0) {
                $message = "Data service berhasil diimpor! {$importedCount} data baru ditambahkan.";
                if ($skippedCount > 0) {
                    $message .= " {$skippedCount} data duplikat dilewati.";
                }
                if ($skippedDealerCount > 0) {
                    $message .= " {$skippedDealerCount} data ditolak karena tidak sesuai dengan dealer Anda.";
                }
                return redirect()->back()->with('success', $message);
            }

            $errorMessage = 'Impor gagal.';
            if ($skippedDealerCount > 0) {
                 $errorMessage .= " {$skippedDealerCount} baris data ditolak karena tidak sesuai dengan dealer Anda.";
            }
            if ($skippedCount > 0) {
                $errorMessage .= " {$skippedCount} baris data merupakan duplikat.";
            }
            if ($importedCount == 0 && $skippedCount == 0 && $skippedDealerCount == 0) {
                $errorMessage = 'Tidak ada data yang ditemukan di dalam file.';
            }

            return redirect()->back()->with('error', $errorMessage);

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan saat mengimpor file: ' . $e->getMessage());
        }
    }

    public function show(Service $service)
    {
        $this->authorize('view-service');
        $service->load('details', 'lokasi');
        return view('admin.services.show', compact('service'));
    }

    public function edit(Service $service)
    {
        $this->authorize('manage-service');
        $service->load('details.part', 'lokasi');
        $userLokasiId = Auth::user()->gudang_id;
        return view('admin.services.edit', compact('service', 'userLokasiId'));
    }

    public function update(Request $request, Service $service)
    {
        $this->authorize('manage-service');

        $validated = $request->validate([
            'items' => 'sometimes|array',
            'items.*.part_id' => 'required|exists:parts,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        try {
            DB::transaction(function () use ($validated, $service) {

                $user = Auth::user();
                if (!$user->gudang_id) {
                    throw new \Exception("Gagal menyimpan: Akun Anda tidak terhubung dengan lokasi manapun.");
                }
                $lokasiId = $user->gudang_id;

                if (isset($validated['items'])) {
                    foreach ($validated['items'] as $item) {
                        $part = Part::find($item['part_id']);
                        $quantity = (int)$item['quantity'];

                        $remainingQtyToReduce = $quantity;
                        $batches = InventoryBatch::where('part_id', $part->id)
                            ->where('gudang_id', $lokasiId)
                            ->where('quantity', '>', 0)
                            ->orderBy('created_at', 'asc')
                            ->get();

                        if ($batches->sum('quantity') < $remainingQtyToReduce) {
                            $namaLokasi = $user->lokasi->nama_lokasi ?? 'gudang Anda';
                            throw new \Exception("Stok untuk part '{$part->nama_part}' tidak mencukupi di {$namaLokasi}.");
                        }

                        foreach ($batches as $batch) {
                            if ($remainingQtyToReduce <= 0) break;
                            $qtyToTake = min($batch->quantity, $remainingQtyToReduce);
                            $stokTotalSebelum = InventoryBatch::where('part_id', $part->id)->where('gudang_id', $lokasiId)->sum('quantity');

                            $batch->decrement('quantity', $qtyToTake);
                            $remainingQtyToReduce -= $qtyToTake;

                            StockMovement::create([
                                'part_id' => $part->id, 'gudang_id' => $lokasiId, 'rak_id' => $batch->rak_id,
                                'jumlah' => -$qtyToTake, 'stok_sebelum' => $stokTotalSebelum, 'stok_sesudah' => $stokTotalSebelum - $qtyToTake,
                                'referensi_type' => get_class($service), 'referensi_id' => $service->id,
                                'keterangan' => 'Penambahan part pada Service #' . $service->invoice_no,
                                'user_id' => Auth::id(),
                            ]);
                        }

                        $service->details()->create([
                            'item_category' => 'PART', 'item_code' => $part->kode_part, 'item_name' => $part->nama_part,
                            'quantity' => $quantity, 'price' => $item['price'],
                        ]);
                    }
                }

                $service->refresh()->load('details');
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
        $service = Service::with('details')->findOrFail($id);

        // ++ PERUBAHAN: Tandai faktur sebagai sudah dicetak/didownload ++
        if (is_null($service->printed_at)) {
            $service->printed_at = now();
            $service->save();
        }

        $fileName = 'Invoice-' . $service->invoice_no . '.pdf';
        $widthInPoints = 24 * 28.3465;
        $heightInPoints = 14 * 28.3465;
        $customPaper = [0, 0, $heightInPoints, $widthInPoints];

        $pdf = PDF::loadView('admin.services.pdf', compact('service'))
            ->setPaper($customPaper)
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'dpi' => 150,
                'defaultFont' => 'Courier',
                'margin-top'    => 0,
                'margin-right'  => 0,
                'margin-bottom' => 0,
                'margin-left'   => 0,
            ]);

        return $pdf->stream($fileName);
    }
}
