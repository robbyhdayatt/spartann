<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DailyReport;
use App\Imports\DailyReportImport;
use Maatwebsite\Excel\Facades\Excel;
use PDF;

class DailyReportController extends Controller
{
    public function index(Request $request)
    {
        $query = DailyReport::latest();

        // Logika untuk filter tanggal
        if ($request->has('date_range') && $request->date_range) {
            $dates = explode(' - ', $request->date_range);
            if (count($dates) == 2) {
                $startDate = \Carbon\Carbon::createFromFormat('d/m/Y', $dates[0])->startOfDay();
                $endDate = \Carbon\Carbon::createFromFormat('d/m/Y', $dates[1])->endOfDay();
                $query->whereBetween('reg_date', [$startDate, $endDate]);
            }
        }

        $reports = $query->paginate(25)->withQueryString(); // withQueryString() agar filter tetap ada saat pindah halaman

        return view('admin.daily_reports.index', compact('reports'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xls,xlsx,csv'
        ]);

        try {
            Excel::import(new DailyReportImport, $request->file('file'));

            return redirect()->back()->with('success', 'Laporan harian berhasil diimpor!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan saat mengimpor file: ' . $e->getMessage());
        }
    }

    public function show(DailyReport $dailyReport)
    {
        $dailyReport->load('details');
        return view('admin.daily_reports.show', compact('dailyReport'));
    }

    public function downloadPDF($id)
    {
        $dailyReport = \App\Models\DailyReport::with('details')->findOrFail($id);
        $fileName = 'Invoice-' . $dailyReport->invoice_no . '.pdf';

        // Ukuran 24x14 cm dalam points
        $width = 24 * 28.3465; // 680.31 pt
        $height = 14 * 28.3465; // 396.85 pt

        // DOMPDF butuh ukuran landscape = [0, 0, tinggi, lebar]
        $customPaper = [0, 0, $height, $width];

        $pdf = \PDF::loadView('admin.daily_reports.pdf', compact('dailyReport'))
            ->setPaper($customPaper) // jangan pakai 'landscape' di sini!
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

        return $pdf->stream($fileName); // pakai stream supaya bisa langsung print
    }

}
