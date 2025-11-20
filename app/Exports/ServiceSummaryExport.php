<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ServiceSummaryExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    protected $startDate;
    protected $endDate;
    protected $invoiceNo;

    public function __construct($startDate, $endDate, $invoiceNo)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->invoiceNo = $invoiceNo;
    }

    public function collection()
    {
        $query = DB::table('service_details')
            ->join('services', 'service_details.service_id', '=', 'services.id')
            ->leftJoin('barangs', 'service_details.barang_id', '=', 'barangs.id')
            ->select(
                'service_details.item_code',
                'service_details.item_name',
                'service_details.item_category',
                DB::raw('SUM(service_details.quantity) as total_qty'),
                DB::raw('SUM(service_details.price + service_details.labor_cost_service) as total_penjualan'),
                DB::raw("SUM(CASE
                                WHEN service_details.item_category != 'JASA' THEN service_details.quantity * COALESCE(barangs.selling_in, 0)
                                ELSE 0
                            END) as total_modal"),
                DB::raw("SUM(service_details.price + service_details.labor_cost_service) -
                         SUM(CASE
                                WHEN service_details.item_category != 'JASA' THEN service_details.quantity * COALESCE(barangs.selling_in, 0)
                                ELSE 0
                            END) as total_keuntungan")
            )
            ->whereBetween('services.reg_date', [$this->startDate, $this->endDate])
            ->groupBy('service_details.item_code', 'service_details.item_name', 'service_details.item_category')
            ->orderBy('total_qty', 'desc');

        if ($this->invoiceNo) {
            $query->where('services.invoice_no', 'like', '%' . $this->invoiceNo . '%');
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Kode Item',
            'Nama Item',
            'Kategori',
            'Qty Terjual',
            'Total Penjualan',
            'Total Modal (HPP)',
            'Total Profit',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
