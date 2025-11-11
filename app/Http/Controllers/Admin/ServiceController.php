<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Service;
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
        $this->authorize('view-service');

        $user = Auth::user();
        $query = Service::query();
        $dealers = collect();
        $selectedDealer = null;
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $canFilterByDealer = $user->jabatan && in_array($user->jabatan->singkatan, ['SA', 'PIC']);

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
        $service->load('details', 'lokasi');
        return view('admin.services.show', compact('service'));
    }

    public function downloadPDF($id)
    {
        $this->authorize('view-service');
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
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $isSuperAdminOrPic = $user->jabatan && in_array($user->jabatan->singkatan, ['SA', 'PIC']);

        if (!$startDate || !$endDate) {
            return redirect()->back()->with('error', 'Silakan pilih Tanggal Mulai dan Tanggal Selesai untuk export.');
        }

        try {
            $validStartDate = Carbon::createFromFormat('Y-m-d', $startDate)->format('Y-m-d');
            $validEndDate = Carbon::createFromFormat('Y-m-d', $endDate)->format('Y-m-d');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Format tanggal export tidak valid.');
        }

        $dealerCodeForExport = null;
        $dealerName = 'Semua_Dealer';

        if ($isSuperAdminOrPic) {
            if ($selectedDealer && $selectedDealer !== 'all') {
                $dealerCodeForExport = $selectedDealer;
                $dealerInfo = Lokasi::where('kode_lokasi', $dealerCodeForExport)->first();
                $dealerName = $dealerInfo ? str_replace(' ', '_', $dealerInfo->nama_lokasi) : $dealerCodeForExport;
            }
        } else {
            if ($user->lokasi && $user->lokasi->kode_lokasi) {
                $dealerCodeForExport = $user->lokasi->kode_lokasi;
                $dealerName = str_replace(' ', '_', $user->lokasi->nama_lokasi);
            } else {
                return redirect()->back()->with('error', 'Akun Anda tidak terasosiasi dengan dealer.');
            }
        }

        $fileName = "Laporan_Service_{$dealerName}_{$validStartDate}_sd_{$validEndDate}.xlsx";

        return Excel::download(new ServiceDailyReportExport($dealerCodeForExport, $validStartDate, $validEndDate), $fileName);
    }
}
