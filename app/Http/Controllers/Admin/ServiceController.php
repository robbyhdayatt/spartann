<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Service;
use App\Imports\ServiceImport;
use Maatwebsite\Excel\Facades\Excel;
use PDF;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $services = Service::latest()->paginate(25);

        // Nama variabel diubah menjadi 'services'
        return view('admin.services.index', compact('services'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xls,xlsx,csv'
        ]);

        try {
            $import = new ServiceImport; // Diubah dari DailyReportImport
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

    public function show(Service $service) // Diubah dari DailyReport $dailyReport
    {
        $service->load('details'); // Diubah dari $dailyReport
        // Nama variabel diubah menjadi 'service'
        return view('admin.services.show', compact('service'));
    }

    public function downloadPDF($id)
    {
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
