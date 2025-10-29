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
        $purchaseOrder->load(['supplier', 'lokasi', 'details.part', 'createdBy']);
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
        $penjualan->load(['konsumen', 'lokasi', 'sales', 'details.part']);
        $data = ['penjualan' => $penjualan];

        // 1. Tentukan ukuran kustom 24cm x 12cm (lebar x tinggi)
        // [0, 0, tinggi_pts, lebar_pts]
        $customPaper = [0, 0, 339.84, 680.30]; // 12cm x 24cm

        // 2. Load view 'admin.penjualans.print' (ini adalah template HTML)
        $pdf = PDF::loadView('admin.penjualans.print', $data);

        // 3. Atur kertas ke ukuran kustom & orientasi landscape
        // (Orientasi landscape akan otomatis jika lebar > tinggi)
        $pdf->setPaper($customPaper, 'landscape');

        // 4. Atur opsi untuk menghilangkan margin (mentok sisi)
        $pdf->setOptions([
            'defaultFont' => 'sans-serif', // Gunakan font standar
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'margin_left' => 0,
            'margin_right' => 0,
        ]);

        return $pdf->download('Faktur-' . $penjualan->nomor_faktur . '.pdf');
    }
}
