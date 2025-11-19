<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\Penjualan;
use PDF; // Pastikan ini adalah alias untuk \Barryvdh\DomPDF\Facade::class

class PdfController extends Controller
{
    public function purchaseOrder(PurchaseOrder $purchaseOrder)
    {
        // ... (kode PO Anda tetap sama) ...
        $purchaseOrder->load(['supplier', 'lokasi', 'details.barang', 'createdBy']);
        $data = ['purchaseOrder' => $purchaseOrder];
        $pdf = PDF::loadView('admin.purchase_orders.print', $data)
                    ->setPaper([0, 0, 396, 684], 'landscape');
        return $pdf->download('PO-' . $purchaseOrder->nomor_po . '.pdf');
    }

    /**
     * Generate PDF Faktur Penjualan
     */
    public function penjualan(Penjualan $penjualan)
    {
        // Memuat relasi yang benar (sesuai langkah sebelumnya)
        $penjualan->load(['konsumen', 'lokasi', 'sales', 'details.barang']);
        $data = ['penjualan' => $penjualan];

        // === PENGATURAN KERTAS DISAMAKAN DENGAN SERVICE CONTROLLER ===

        // 1. Tentukan ukuran 24cm x 14cm
        $width_cm = 24;
        $height_cm = 14;
        $points_per_cm = 28.3465; // Konversi cm ke points (1pt = 1/72 inch, 1 inch = 2.54cm)

        // dompdf menggunakan [0, 0, width_pts, height_pts]
        // Jika width > height, otomatis menjadi landscape
        $widthInPoints = $width_cm * $points_per_cm; // 680.3
        $heightInPoints = $height_cm * $points_per_cm; // 396.8

        $customPaper = [0, 0, $widthInPoints, $heightInPoints];

        // 2. Load view 'admin.penjualans.print'
        $pdf = PDF::loadView('admin.penjualans.print', $data);

        // 3. Atur kertas ke ukuran kustom
        $pdf->setPaper($customPaper);

        // 4. Atur opsi (margin 0, font Arial, dll) agar sama persis dengan ServiceController
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'dpi' => 150,
            'defaultFont' => 'Arial', // Samakan font
            'margin-top'    => 0, // Hapus margin
            'margin-right'  => 0,
            'margin-bottom' => 0,
            'margin-left'   => 0,
            'enable-smart-shrinking' => true,
            'disable-smart-shrinking' => false,
            'lowquality' => false
        ]);

        // === SELESAI PENGATURAN KERTAS ===

        return $pdf->download('Faktur-' . $penjualan->nomor_faktur . '.pdf');
    }
}
