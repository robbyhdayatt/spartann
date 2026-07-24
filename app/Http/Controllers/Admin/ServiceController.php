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
use Yajra\DataTables\Facades\DataTables; 

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        // =========================================================================
        // [MODIFIKASI] MAINTENANCE MODE: Mengarahkan halaman ke view maintenance
        // Hapus atau jadikan komentar (//) baris di bawah ini jika perbaikan sudah selesai
        // =========================================================================
        // return view('admin.maintenance');

        $this->authorize('view-service');

        $user = Auth::user();
        $query = Service::with('lokasi'); 
        $dealers = collect();

        if ($request->filled('start_date') || $request->filled('end_date')) {
            session([
                'service.start_date' => $request->input('start_date'),
                'service.end_date' => $request->input('end_date'),
            ]);
        }
        
        if ($request->has('dealer_code')) {
            session(['service.dealer_code' => $request->input('dealer_code')]);
        }

        $startDate = $request->input('start_date', session('service.start_date', now()->toDateString()));
        $endDate = $request->input('end_date', session('service.end_date', now()->toDateString()));
        $canFilterByDealer = $user->isGlobal() || ($user->isPusat() && $user->hasRole(['ASD', 'ACC']));
        
        $selectedDealer = null;

        if ($canFilterByDealer) {
            $dealers = Lokasi::where('tipe', 'DEALER')->orderBy('kode_lokasi')->get(['kode_lokasi', 'nama_lokasi']);
            $selectedDealer = $request->input('dealer_code', session('service.dealer_code'));

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
                $start = Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
                $end = Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay();
                
                $query->whereBetween('services.created_at', [$start, $end]);

            } catch (\Exception $e) {
                $query->whereDate('services.created_at', today());
            }
        }

        if ($request->ajax()) {
            // [MODIFIKASI] Hapus orderBy default di sini agar tidak bertabrakan dengan DataTables
            $data = $query->select('services.*');
            
            return DataTables::of($data)
                ->addIndexColumn()
                ->editColumn('reg_date', function($row) {
                    return $row->reg_date ? Carbon::parse($row->reg_date)->format('d M Y') : '-';
                })
                ->editColumn('customer_name', function($row) {
                    return $row->customer_name ?? '-';
                })
                ->editColumn('total_amount', function($row) {
                    // Cek apakah ini transaksi Part Retail
                    $isPartRetail = stripos($row->service_order ?? '', 'part') !== false;
                    
                    if ($isPartRetail) {
                        // Jika Part Retail, mutlak ambil dari total_amount
                        $total = $row->total_amount;
                    } else {
                        // Jika service biasa, ambil total_payment. Tapi jika payment 0, fallback ke total_amount
                        $total = ($row->total_payment > 0) ? $row->total_payment : $row->total_amount;
                    }
                    
                    // Pastikan diubah ke float agar tidak error jika null, lalu diformat
                    return 'Rp ' . number_format((float)($total ?? 0), 0, ',', '.');
                })
                ->addColumn('dealer', function($row) {
                    return $row->lokasi ? $row->lokasi->nama_lokasi : $row->dealer_code;
                })
                ->addColumn('aksi', function($row) {
                    $showUrl = route('admin.services.show', $row->id);
                    return '<a href="'.$showUrl.'" class="btn btn-sm btn-info shadow-sm" title="Lihat Detail"><i class="fas fa-eye mr-1"></i> Detail</a>';
                })
                // [MODIFIKASI] Logika Sorting Kustom: "Belum Cetak" Diutamakan, disusul Tanggal Terbaru
                ->orderColumn('printed_at', function ($query, $order) {
                    if ($order === 'asc') {
                        $query->orderByRaw('CASE WHEN printed_at IS NULL THEN 0 ELSE 1 END ASC')->orderBy('created_at', 'desc');
                    } else {
                        $query->orderByRaw('CASE WHEN printed_at IS NULL THEN 0 ELSE 1 END DESC')->orderBy('created_at', 'desc');
                    }
                })
                ->rawColumns(['aksi'])
                ->make(true);
        }

        // [MODIFIKASI] Terapkan logika sorting yang sama pada fallback non-AJAX
        $services = $query->orderByRaw('CASE WHEN printed_at IS NULL THEN 0 ELSE 1 END ASC')
                          ->orderBy('created_at', 'desc')
                          ->paginate(50000)
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
        $this->authorize('manage-service');
        
        $request->validate([
            'file' => 'required|mimes:xls,xlsx,csv',
            'tanggal_laporan' => 'required|date' 
        ]);

        try {
            $user = Auth::user();
            if (!$user->lokasi || !$user->lokasi->kode_lokasi) {
                return redirect()->back()->with('error', 'Gagal mengimpor: Akun Anda tidak terasosiasi dengan dealer manapun.');
            }
            $userDealerCode = $user->lokasi->kode_lokasi;
            $tanggalLaporan = $request->input('tanggal_laporan'); 

            $import = new ServiceImport($userDealerCode, $tanggalLaporan); 
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
                
                if (!empty($errors)) {
                    return redirect()->back()->with('success', $message)->with('import_errors', $errors);
                }
                
                return redirect()->back()->with('success', $message);
            }
            
            $errorMessage = 'Tidak ada data baru yang diimpor. ' . ($skippedDuplicate > 0 ? "{$skippedDuplicate} data duplikat ditemukan." : "");
            
            if (!empty($errors)) {
                return redirect()->back()->with('error', $errorMessage)->with('import_errors', $errors);
            }

            return redirect()->back()->with('error', $errorMessage);

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan fatal saat membaca file: ' . $e->getMessage());
        }
    }

    public function exportExcel(Request $request)
    {
        $this->authorize('view-service');
        
        $user = Auth::user();
        $startDate = $request->input('start_date') ?? session('service.start_date') ?? now()->toDateString();
        $endDate = $request->input('end_date') ?? session('service.end_date') ?? now()->toDateString();
        $selectedDealer = $request->input('dealer_code') ?? session('service.dealer_code');
        $canFilterByDealer = $user->isGlobal() || ($user->isPusat() && $user->hasRole(['ASD', 'ACC']));
        
        if (!$canFilterByDealer) {
            if ($user->lokasi && $user->lokasi->kode_lokasi) {
                $selectedDealer = $user->lokasi->kode_lokasi;
            } else {
                return redirect()->back()->with('error', 'Akun Anda tidak memiliki dealer yang terkait.');
            }
        } else {
            if (empty($selectedDealer)) {
                $selectedDealer = 'all';
            }
        }

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
        $this->authorize('view-service');
        
        $user = Auth::user();
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
        $this->authorize('view-service');
        
        $service = Service::with('details.barang', 'lokasi')->findOrFail($id); 
        $user = Auth::user();
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

        // [MODIFIKASI] Memaksa browser untuk membuka PDF langsung di tab (Inline)
        // Ini akan mencegah PDF ter-download otomatis ke folder komputer Anda.
        return response()->make($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$fileName.'"'
        ]);
    }

    public function markAsPrinted($id)
    {
        $this->authorize('view-service');
        
        $service = Service::findOrFail($id);
        
        if (is_null($service->printed_at)) {
            $service->printed_at = now();
            $service->save();
        }

        return response()->json(['status' => 'success', 'message' => 'Status cetak diperbarui']);
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

        $user = Auth::user();
        if (!$user->lokasi_id) {
            return back()->with('error', 'Akun Anda tidak memiliki lokasi gudang. Stok tidak dapat diproses.');
        }
        $lokasiId = $user->lokasi_id;

        DB::beginTransaction();
        try {
            foreach ($validated['items'] as $item) {
                $barang = Barang::find($item['part_id']);

                if (!$barang->is_active) {
                    throw new \Exception("Gagal Tambah Part! '{$barang->part_name}' statusnya NONAKTIF.");
                }

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