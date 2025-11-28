<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ServiceSummaryExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles, WithColumnFormatting, WithMapping, WithCustomStartCell, WithEvents
{
    protected $startDate;
    protected $endDate;
    protected $invoiceNo;
    
    // Variabel untuk menyimpan Grand Total
    private $totalQty = 0;
    private $totalPenjualan = 0;
    private $totalModal = 0;
    private $totalProfit = 0;
    private $rowNumber = 0;

    public function __construct($startDate, $endDate, $invoiceNo)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->invoiceNo = $invoiceNo;
    }

    public function startCell(): string
    {
        return 'A3'; // Mulai data dari baris 3
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
                
                // PERUBAHAN: Penjualan = Qty * Harga Retail Master Barang
                DB::raw('SUM(service_details.quantity * COALESCE(barangs.retail, 0)) as total_penjualan'),
                
                // Modal (HPP) = Qty * Selling Out
                DB::raw("SUM(service_details.quantity * COALESCE(barangs.selling_out, 0)) as total_modal"),
                
                // Profit = (Qty * Retail) - (Qty * Selling Out)
                DB::raw("SUM(service_details.quantity * COALESCE(barangs.retail, 0)) -
                         SUM(service_details.quantity * COALESCE(barangs.selling_out, 0)) as total_keuntungan")
            )
            ->whereBetween('services.reg_date', [$this->startDate, $this->endDate])
            // FILTER KHUSUS: PARTS ONLY
            ->whereNotNull('service_details.barang_id')
            ->groupBy('service_details.item_code', 'service_details.item_name', 'service_details.item_category')
            ->orderBy('total_qty', 'desc');

        if ($this->invoiceNo) {
            $query->where('services.invoice_no', 'like', '%' . $this->invoiceNo . '%');
        }

        $data = $query->get();

        // Hitung Grand Total
        $this->totalQty = $data->sum('total_qty');
        $this->totalPenjualan = $data->sum('total_penjualan');
        $this->totalModal = $data->sum('total_modal');
        $this->totalProfit = $data->sum('total_keuntungan');

        return $data;
    }

    public function map($row): array
    {
        $this->rowNumber++;
        return [
            $this->rowNumber,
            $row->item_code,
            $row->item_name,
            (str_contains(strtoupper($row->item_category), 'OLI') ? 'OLI' : $row->item_category),
            $row->total_qty,
            $row->total_penjualan,
            $row->total_modal,
            $row->total_keuntungan
        ];
    }

    public function headings(): array
    {
        return [
            'No',
            'Kode Part',
            'Nama Part',
            'Kategori',
            'Qty Terjual',
            'Total Penjualan (Retail)', // Judul disesuaikan agar jelas
            'Total Modal (HPP)',
            'Keuntungan (Profit)',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'E' => '#,##0',
            'F' => '"Rp "#,##0_-',
            'G' => '"Rp "#,##0_-',
            'H' => '"Rp "#,##0_-',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            3 => ['font' => ['bold' => true]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $lastRow = $sheet->getHighestRow();
                $totalRow = $lastRow + 1;

                // 1. Tambahkan Judul Laporan
                $sheet->mergeCells('A1:H1');
                $sheet->setCellValue('A1', 'LAPORAN SERVICE SUMMARY (PARTS ONLY)');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // 2. Tambahkan Periode
                $sheet->mergeCells('A2:H2');
                $period = "Periode: " . \Carbon\Carbon::parse($this->startDate)->format('d M Y') . " s/d " . \Carbon\Carbon::parse($this->endDate)->format('d M Y');
                $sheet->setCellValue('A2', $period);
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['italic' => true, 'size' => 11],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // 3. Styling Header Tabel
                $sheet->getStyle('A3:H3')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4F81BD']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                ]);

                // 4. Styling Baris Data
                if ($lastRow >= 4) {
                    $sheet->getStyle('A4:H' . $lastRow)->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                    ]);
                    $sheet->getStyle('A4:B' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('D4:E' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // 5. Tambahkan Baris Grand Total
                $sheet->setCellValue('A' . $totalRow, 'GRAND TOTAL');
                $sheet->mergeCells('A' . $totalRow . ':D' . $totalRow);
                
                $sheet->setCellValue('E' . $totalRow, $this->totalQty);
                $sheet->setCellValue('F' . $totalRow, $this->totalPenjualan);
                $sheet->setCellValue('G' . $totalRow, $this->totalModal);
                $sheet->setCellValue('H' . $totalRow, $this->totalProfit);

                // Style Grand Total
                $sheet->getStyle('A' . $totalRow . ':H' . $totalRow)->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD9D9D9']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                ]);
                
                $sheet->getStyle('E' . $totalRow)->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('F' . $totalRow . ':H' . $totalRow)->getNumberFormat()->setFormatCode('"Rp "#,##0_-');
                
                $sheet->getStyle('A' . $totalRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('E' . $totalRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}