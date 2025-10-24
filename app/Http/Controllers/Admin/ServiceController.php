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

        // Asumsi 'SA' dan 'PIC' adalah singkatan di tabel Jabatan
        $isSuperAdminOrPic = $user->jabatan && in_array($user->jabatan->singkatan, ['SA', 'PIC']);

        if ($isSuperAdminOrPic) {
            // Ambil dari model Lokasi yang tipe nya DEALER
            $dealers = Lokasi::where('tipe', 'DEALER')->orderBy('kode_gudang')->get(['kode_gudang', 'nama_gudang']);

            $selectedDealer = $request->input('dealer_code');

            if ($selectedDealer && $selectedDealer !== 'all') {
                $query->where('dealer_code', $selectedDealer);
            }
        } else {
            if ($user->lokasi && $user->lokasi->kode_gudang) {
                $query->where('dealer_code', $user->lokasi->kode_gudang);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $services = $query->latest()->paginate(25)->withQueryString();

        // Ganti nama variabel 'dealers' menjadi 'listDealer' agar tidak bentrok
        return view('admin.services.index', [
            'services' => $services,
            'listDealer' => $dealers, // Menggunakan nama variabel baru
            'selectedDealer' => $selectedDealer,
            'isSuperAdminOrPic' => $isSuperAdminOrPic
        ]);
    }

    public function import(Request $request)
    {
        // ... (Fungsi impor tetap sama) ...
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
            if (!$user->lokasi || $service->dealer_code !== $user->lokasi->kode_gudang) {
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
            if (!$user->lokasi || $service->dealer_code !== $user->lokasi->kode_gudang) {
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
            ->setPaper($customPaper) // Hapus 'landscape', biarkan ukuran custom
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'dpi' => 150,
                'defaultFont' => 'Courier',
                'margin-top'    => 0,
                'margin-right'  => 0,
                'margin-bottom' => 0,
                'margin-left'   => 0,
                'enable-smart-shrinking' => true,
                'disable-smart-shrinking' => false,
                'lowquality' => false
            ]);

        return $pdf->stream($fileName);
    }
}

