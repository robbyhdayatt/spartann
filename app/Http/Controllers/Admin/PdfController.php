<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\Penjualan;
use PDF;

class PdfController extends Controller
{
    /**
     * Generate PDF Purchase Order (Disamakan dengan Penjualan)
     */
    public function purchaseOrder(PurchaseOrder $purchaseOrder)
    {
        // Load relasi yang dibutuhkan
        $purchaseOrder->load(['supplier', 'lokasi', 'sumberLokasi', 'details.barang', 'createdBy', 'approvedBy', 'approvedByHead']);
        
        $data = ['purchaseOrder' => $purchaseOrder];

        // === 1. KONFIGURASI KERTAS (Sama dengan Penjualan) ===
        // Ukuran: 24cm x 14cm (Landscape Faktur)
        $width_cm = 24;
        $height_cm = 14;
        $points_per_cm = 28.3465;
        $widthInPoints = $width_cm * $points_per_cm; // ~680.3
        $heightInPoints = $height_cm * $points_per_cm; // ~396.8
        
        $customPaper = [0, 0, $widthInPoints, $heightInPoints];

        // === 2. LOAD VIEW ===
        $pdf = PDF::loadView('admin.purchase_orders.print', $data);

        // === 3. SET PAPER & OPTIONS ===
        $pdf->setPaper($customPaper);

        $pdf->setOptions([
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
            'lowquality' => false
        ]);

        return $pdf->download('PO-' . $purchaseOrder->nomor_po . '.pdf');
    }

    /**
     * Generate PDF Faktur Penjualan
     */
    public function penjualan(Penjualan $penjualan)
    {
        $penjualan->load(['konsumen', 'lokasi', 'sales', 'details.barang']);
        $data = ['penjualan' => $penjualan];

        // 1. Tentukan ukuran 24cm x 14cm
        $width_cm = 24;
        $height_cm = 14;
        $points_per_cm = 28.3465;
        $widthInPoints = $width_cm * $points_per_cm; // 680.3
        $heightInPoints = $height_cm * $points_per_cm; // 396.8

        $customPaper = [0, 0, $widthInPoints, $heightInPoints];

        // 2. Load view 'admin.penjualans.print'
        $pdf = PDF::loadView('admin.penjualans.print', $data);

        // 3. Atur kertas ke ukuran kustom
        $pdf->setPaper($customPaper);

        // 4. Atur opsi
        $pdf->setOptions([
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
            'lowquality' => false
        ]);

        return $pdf->download('Faktur-' . $penjualan->nomor_faktur . '.pdf');
    }
}