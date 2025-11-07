<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Service;
use App\Imports\ServiceImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Auth;
use PDF;
// ++ Tambahkan Model Dealer & Lokasi (jika Dealer = Lokasi, cukup satu) ++
use App\Models\Dealer;
use App\Models\Lokasi; // Pastikan model Lokasi ada
use Illuminate\Support\Facades\DB;
use Carbon\Carbon; // Pastikan ini ada di atas
use App\Exports\ServiceDailyReportExport; // Pastikan ini ada di atas


class ServiceController extends Controller
{
    // ... (method index() dan import() tetap sama) ...
    public function index(Request $request)
    {
        $this->authorize('view-service');

        $user = Auth::user();
        $query = Service::query();
        $dealers = collect();
        $selectedDealer = null;
        $filterDate = $request->input('filter_date', now()->toDateString());

        // Ubah variabel ini
        $canFilterByDealer = $user->jabatan && in_array($user->jabatan->singkatan, ['SA', 'PIC']);

        if ($canFilterByDealer) { // Gunakan variabel baru
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

        // ... (Logika filter tanggal tetap sama) ...
        if ($filterDate) {
            try {
                $validDate = Carbon::createFromFormat('Y-m-d', $filterDate)->startOfDay();
                // Pastikan menggunakan 'services.created_at'
                $query->whereDate('services.created_at', $validDate);
            } catch (\Exception $e) {
                $filterDate = null;
            }
        }

        $services = $query->orderBy('created_at', 'desc')->paginate(1000)->withQueryString();

        return view('admin.services.index', [
            'services' => $services,
            'listDealer' => $dealers,
            'selectedDealer' => $selectedDealer,
            // 'isSuperAdminOrPic' => $isSuperAdminOrPic, // Ganti ini
            'canFilterByDealer' => $canFilterByDealer, // Kirim variabel baru
            'filterDate' => $filterDate
        ]);
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

            $errorMessage = 'Impor gagal.';
            if ($skippedDealerCount > 0) {
                 $errorMessage .= " {$skippedDealerCount} baris data ditolak karena tidak sesuai dengan dealer Anda.";
            }
            if ($skippedCount > 0) {
                $errorMessage .= " {$skippedCount} baris data merupakan duplikat atau gagal (cek log).";
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
        $user = Auth::user();
        $isSuperAdminOrPic = $user->hasRole(['SA', 'PIC']);

        if (!$isSuperAdminOrPic) {
            if (!$user->lokasi || $service->dealer_code !== $user->lokasi->kode_lokasi) {
                abort(403, 'Anda tidak diizinkan melihat detail service ini.');
            }
        }

        $this->authorize('view-service');
        // Pastikan 'lokasi' dimuat di sini juga untuk tampilan 'show'
        $service->load('details', 'lokasi');
        return view('admin.services.show', compact('service'));
    }

    public function downloadPDF($id)
    {
        $this->authorize('view-service');

        // ++ PERUBAHAN: Tambahkan 'lokasi' ke with() ++
        $service = Service::with('details', 'lokasi')->findOrFail($id);

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
                ->setOptions([
                    'isHtml5ParserEnabled' => true,
                    'isRemoteEnabled' => true,
                    'dpi' => 150,
                    // Pastikan ini 'Arial' untuk printer dot-matrix
                    'defaultFont' => 'Arial',
                    'margin-top'    => 0,
                    'margin-right'  => 0,
                    'margin-bottom' => 0,
                    'margin-left'   => 0,
                    'enable-smart-shrinking' => true,
                    'disable-smart-shrinking' => false,
                    'lowquality' => false,
                    'enable_php' => true,
                ]);

            return $pdf->stream($fileName);
    }

    public function exportExcel(Request $request)
    {
        $this->authorize('export-service-report');

        $user = Auth::user();
        $selectedDealer = $request->query('dealer_code');
        $filterDate = $request->query('filter_date');

        $isSuperAdminOrPic = $user->jabatan && in_array($user->jabatan->singkatan, ['SA', 'PIC']);

        // Validasi tanggal
        if (!$filterDate) {
            return redirect()->back()->with('error', 'Silakan pilih tanggal untuk export laporan harian.');
        }

        try {
            $validDate = Carbon::createFromFormat('Y-m-d', $filterDate)->format('Y-m-d');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Format tanggal export tidak valid.');
        }

        $dealerCodeForExport = null;
        $dealerName = 'Semua_Dealer';

        if ($isSuperAdminOrPic) {
            if ($selectedDealer && $selectedDealer !== 'all') {
                $dealerCodeForExport = $selectedDealer;
                // Cari nama dealer untuk nama file
                $dealerInfo = Lokasi::where('kode_lokasi', $dealerCodeForExport)->first();
                $dealerName = $dealerInfo ? str_replace(' ', '_', $dealerInfo->nama_lokasi) : $dealerCodeForExport;
            }
        } else {
            // Jika bukan SA/PIC, otomatis filter berdasarkan dealernya
            if ($user->lokasi && $user->lokasi->kode_lokasi) {
                $dealerCodeForExport = $user->lokasi->kode_lokasi;
                $dealerName = str_replace(' ', '_', $user->lokasi->nama_lokasi);
            } else {
                // Seharusnya tidak terjadi jika logic di index benar
                return redirect()->back()->with('error', 'Akun Anda tidak terasosiasi dengan dealer.');
            }
        }


        $fileName = "Laporan_Service_Harian_{$dealerName}_{$validDate}.xlsx";

        // Kirim parameter filter ke class Export
        return Excel::download(new ServiceDailyReportExport($dealerCodeForExport, $validDate), $fileName);
    }
}

