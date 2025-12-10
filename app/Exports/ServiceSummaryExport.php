<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ServiceSummaryExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles, WithColumnFormatting, WithEvents
{
    protected $startDate;
    protected $endDate;
    protected $invoiceNo;
    protected $lokasiId;

    // Variabel untuk menampung total
    protected $grandQty = 0;
    protected $grandJual = 0;
    protected $grandModal = 0;
    protected $grandProfit = 0;
    protected $rowCount = 0;

    public function __construct($startDate, $endDate, $invoiceNo, $lokasiId = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->invoiceNo = $invoiceNo;
        $this->lokasiId = $lokasiId;
    }

    public function collection()
    {
        // 1. Ambil daftar kode part yang valid (sama seperti di Controller)
        $validPartCodes = DB::table('converts_main')->distinct()->pluck('part_code');

        // 2. Query Utama (Copy logic dari ReportController::serviceSummary)
        $query = DB::table('service_details')
            ->join('services', 'service_details.service_id', '=', 'services.id')
            ->join('barangs', 'service_details.barang_id', '=', 'barangs.id')
            ->whereIn('service_details.item_code', $validPartCodes)
            ->select(
                'service_details.item_code',
                'barangs.part_name as item_name',
                'service_details.item_category',
                DB::raw('SUM(service_details.quantity) as total_qty'),
                
                // Perhitungan Keuangan
                DB::raw('SUM(service_details.quantity * COALESCE(barangs.retail, 0)) as total_penjualan'),
                DB::raw("SUM(service_details.quantity * COALESCE(barangs.selling_out, 0)) as total_modal"), // Pakai selling_out
                DB::raw("SUM(service_details.quantity * COALESCE(barangs.retail, 0)) -
                         SUM(service_details.quantity * COALESCE(barangs.selling_out, 0)) as total_keuntungan")
            )
            ->whereBetween('services.reg_date', [$this->startDate, $this->endDate])
            ->groupBy('service_details.item_code', 'barangs.part_name', 'service_details.item_category')
            ->orderBy('total_qty', 'desc');

        // 3. Terapkan Filter Lokasi & Invoice
        if ($this->lokasiId) {
            $query->where('services.lokasi_id', $this->lokasiId);
        }

        if ($this->invoiceNo) {
            $query->where('services.invoice_no', 'like', '%' . $this->invoiceNo . '%');
        }

        $data = $query->get();

        // 4. Hitung Grand Total saat fetching data
        $this->grandQty = $data->sum('total_qty');
        $this->grandJual = $data->sum('total_penjualan');
        $this->grandModal = $data->sum('total_modal');
        $this->grandProfit = $data->sum('total_keuntungan');
        $this->rowCount = $data->count();

        return $data;
    }

    public function headings(): array
    {
        return [
            'Kode Part',
            'Nama Barang',
            'Kategori',
            'Total Qty',
            'Total Penjualan',
            'Total Modal (HPP)',
            'Total Keuntungan',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Styling Header (Baris 1)
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => '4B5563'], // Abu-abu gelap
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'D' => '#,##0', // Qty
            'E' => '#,##0', // Jual
            'F' => '#,##0', // Modal
            'G' => '#,##0', // Untung
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                // Menentukan baris Grand Total
                $lastRow = $this->rowCount + 1; // Header
                $totalRow = $lastRow + 1;

                // Tulis "GRAND TOTAL"
                $sheet->setCellValue('A' . $totalRow, 'GRAND TOTAL');
                $sheet->mergeCells('A' . $totalRow . ':C' . $totalRow); // Merge A-C

                // Tulis Nilai Total
                $sheet->setCellValue('D' . $totalRow, $this->grandQty);
                $sheet->setCellValue('E' . $totalRow, $this->grandJual);
                $sheet->setCellValue('F' . $totalRow, $this->grandModal);
                $sheet->setCellValue('G' . $totalRow, $this->grandProfit);

                // Styling Baris Total
                $sheet->getStyle('A' . $totalRow . ':G' . $totalRow)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'E5E7EB'], // Abu-abu muda
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                // Alignment text "GRAND TOTAL" ke kanan
                $sheet->getStyle('A' . $totalRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Format angka baris total
                $sheet->getStyle('D' . $totalRow . ':G' . $totalRow)->getNumberFormat()->setFormatCode('#,##0');

                // Tambahkan Border ke seluruh tabel
                $sheet->getStyle('A1:G' . $totalRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => '000000'],
                        ],
                    ],
                ]);
            },
        ];
    }
}