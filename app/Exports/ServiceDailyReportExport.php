<?php

namespace App\Exports;

use App\Models\Service;
use App\Models\Lokasi;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ServiceDailyReportExport extends DefaultValueBinder implements
    FromQuery,
    WithMapping,
    WithHeadings,
    ShouldAutoSize,
    WithEvents,
    WithStyles,
    WithCustomValueBinder
{
    protected $dealerCode;
    protected $startDate;
    protected $endDate;
    protected $dealers;
    private $totalRows = 0;
    private $headerRowCount = 2;
    private $rowNumber = 0;

    public function __construct($dealerCode, $startDate, $endDate)
    {
        $this->dealerCode = $dealerCode;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->dealers = Lokasi::where('tipe', 'DEALER')->pluck('nama_lokasi', 'kode_lokasi');
    }

    public function bindValue(Cell $cell, $value)
    {
        // Format Text
        if (in_array($cell->getColumn(), ['M', 'P', 'S', 'W'])) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        
        // Format Rupiah (AC s/d AO)
        $moneyColumns = ['AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO'];
        if (in_array($cell->getColumn(), $moneyColumns)) {
             if (is_numeric($value)) {
                 $cell->setValueExplicit($value, DataType::TYPE_NUMERIC);
                 $cell->getStyle()->getNumberFormat()->setFormatCode("Rp #,##0_);(Rp #,##0)");
                 return true;
             }
        }
        return parent::bindValue($cell, $value);
    }

    public function query(): Builder
    {
        $query = Service::query()
                 ->with(['lokasi', 'details'])
                 ->orderBy('created_at', 'desc');

        if ($this->startDate && $this->endDate) {
            $start = Carbon::parse($this->startDate)->startOfDay();
            $end = Carbon::parse($this->endDate)->endOfDay();
            $query->whereBetween('services.created_at', [$start, $end]);
        } elseif ($this->startDate) {
            $query->whereDate('services.created_at', '>=', $this->startDate);
        }

        if ($this->dealerCode && $this->dealerCode !== 'all') {
            $query->where('dealer_code', $this->dealerCode);
        }

        $this->totalRows = (clone $query)->count();
        return $query;
    }

    public function map($service): array
    {
        $this->rowNumber++;
        $namaDealer = $this->dealers->get($service->dealer_code) ?? $service->dealer_code;

        $detailCols = [
            'service_category_code' => [],
            'service_package_name' => [],
            'labor_cost_service' => [],
            'item_code' => [],
            'item_name' => [],
            'quantity' => [],
            'price' => [],
        ];

        foreach ($service->details as $detail) {
            $detailCols['service_category_code'][] = $detail->service_category_code;
            $detailCols['service_package_name'][] = $detail->service_package_name;
            $detailCols['labor_cost_service'][] = $this->formatRupiahExcel($detail->labor_cost_service);
            $detailCols['item_code'][] = $detail->item_code;
            $detailCols['item_name'][] = $detail->item_name;
            $detailCols['quantity'][] = $detail->quantity;
            $detailCols['price'][] = $this->formatRupiahExcel($detail->price);
        }

        $separator = PHP_EOL;

        return [
            $this->rowNumber,
            $service->yss,
            $service->dealer_code,
            $namaDealer,
            $service->point,
            $service->reg_date ? Carbon::parse($service->reg_date)->format('Y-m-d') : null,
            $service->service_order,
            $service->plate_no,
            $service->work_order_no,
            $service->work_order_status,
            $service->invoice_no,
            $service->customer_name,
            $service->customer_ktp,
            $service->customer_npwp_no,
            $service->customer_npwp_name,
            $service->customer_phone,
            $service->mc_brand,
            $service->mc_model_name,
            $service->mc_frame_no,
            implode($separator, $detailCols['service_category_code']),
            implode($separator, $detailCols['service_package_name']),
            implode($separator, $detailCols['labor_cost_service']),
            implode($separator, $detailCols['item_code']),
            implode($separator, $detailCols['item_name']),
            implode($separator, $detailCols['quantity']),
            implode($separator, $detailCols['price']),
            $service->payment_type,
            $service->transaction_code,
            $service->e_payment_amount,
            $service->cash_amount,
            $service->debit_amount,
            $service->total_down_payment,
            $service->total_labor,
            $service->total_part_service,
            $service->total_oil_service,
            $service->total_retail_parts,
            $service->total_retail_oil,
            $service->total_amount,
            $service->benefit_amount,
            $service->total_payment,
            $service->balance,
            $service->technician_name,
            $service->printed_at ? Carbon::parse($service->printed_at)->format('Y-m-d H:i:s') : null,
            $service->created_at ? Carbon::parse($service->created_at)->format('Y-m-d H:i:s') : null,
        ];
    }

    private function formatRupiahExcel($number)
    {
        if (!is_numeric($number)) return $number;
        return 'Rp ' . number_format($number, 0, ',', '.');
    }

    public function headings(): array
    {
        return [
            'No.',
            'YSS', 'Kode Dealer', 'Nama Dealer', 'Point', 'Tanggal Service', 'Service Order',
            'No Polisi', 'No Work Order', 'Status Work Order', 'No Invoice',
            'Nama Pelanggan', 'KTP Pelanggan', 'NPWP No Pelanggan', 'NPWP Nama Pelanggan', 'Telepon Pelanggan',
            'Brand Motor', 'Model Motor', 'No Rangka Motor',
            'Service Category', 'Service Package', 'Labor cost Service', 'Parts No.', 'Parts Name', 'Parts Qty', 'Parts Price',
            'Tipe Pembayaran', 'Kode Transaksi', 'Jumlah E-Payment', 'Jumlah Cash', 'Jumlah Debit',
            'Total DP', 'Total Labor', 'Total Part Service', 'Total Oil Service',
            'Total Retail Parts', 'Total Retail Oil',
            'Total Amount (Gross)', 'Benefit Amount', 'Total Payment (Net)', 'Balance',
            'Nama Teknisi', 'Waktu Cetak', 'Tanggal Import',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $sheet->insertNewRowBefore(1, 1);
                $groupHeaderRow = 1;
                $headerRow = 2;

                $headings = $this->headings();
                $numHeadings = count($headings);
                $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($numHeadings);
                
                $groupHeaders = [
                    'L' => ['range' => "L{$groupHeaderRow}:P{$groupHeaderRow}", 'title' => 'Customer Information'],
                    'Q' => ['range' => "Q{$groupHeaderRow}:S{$groupHeaderRow}", 'title' => 'M/C Information'],
                    'T' => ['range' => "T{$groupHeaderRow}:Z{$groupHeaderRow}", 'title' => 'Service Details'],
                    'AF' => ['range' => "AF{$groupHeaderRow}:AK{$groupHeaderRow}", 'title' => 'Amount Breakdown'],
                    'AL' => ['range' => "AL{$groupHeaderRow}:AO{$groupHeaderRow}", 'title' => 'Totals & Balance']
                ];

                foreach ($groupHeaders as $startCol => $group) {
                    $sheet->mergeCells($group['range']);
                    $sheet->setCellValue("{$startCol}{$groupHeaderRow}", $group['title']);
                }

                for ($colIndex = 1; $colIndex <= $numHeadings; $colIndex++) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                    // Check if inside group
                    $inGroup = false;
                    foreach($groupHeaders as $g) {
                        list($start, $end) = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::rangeBoundaries($g['range']);
                        if ($colIndex >= $start[0] && $colIndex <= $end[0]) $inGroup = true;
                    }
                    if (!$inGroup) {
                        $headerValue = $sheet->getCell("{$colLetter}{$headerRow}")->getValue();
                        $sheet->setCellValue("{$colLetter}{$groupHeaderRow}", $headerValue);
                        $sheet->mergeCells("{$colLetter}{$groupHeaderRow}:{$colLetter}{$headerRow}");
                    }
                }

                $headerStyle = [
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4F81BD']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                ];
                $sheet->getStyle("A{$groupHeaderRow}:{$lastColLetter}{$headerRow}")->applyFromArray($headerStyle);
                $sheet->getRowDimension($groupHeaderRow)->setRowHeight(25);
                $sheet->getRowDimension($headerRow)->setRowHeight(40);

                if ($this->totalRows > 0) {
                    $firstDataRow = $this->headerRowCount + 1;
                    $lastDataRow = $this->totalRows + $this->headerRowCount;
                    
                    $totalRow = $lastDataRow + 1;
                    $totalNonKSGRow = $lastDataRow + 2;

                    // Kolom yang dijumlahkan (Termasuk AK)
                    $columnsToSum = ['AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO'];
                    $mergeUntilCol = 'AB';

                    // 1. Total Semua
                    $sheet->setCellValue("A{$totalRow}", 'TOTAL SEMUA');
                    $sheet->mergeCells("A{$totalRow}:{$mergeUntilCol}{$totalRow}");
                    foreach ($columnsToSum as $column) {
                        $sheet->setCellValue("{$column}{$totalRow}", "=SUM({$column}{$firstDataRow}:{$column}{$lastDataRow})");
                        $sheet->getStyle("{$column}{$totalRow}")->getNumberFormat()->setFormatCode("Rp #,##0_);(Rp #,##0)");
                    }

                    // 2. Total Tanpa KSG
                    $sheet->setCellValue("A{$totalNonKSGRow}", 'TOTAL (TANPA KSG)');
                    $sheet->mergeCells("A{$totalNonKSGRow}:{$mergeUntilCol}{$totalNonKSGRow}");
                    foreach ($columnsToSum as $column) {
                        $rangeCategory = "T{$firstDataRow}:T{$lastDataRow}";
                        $rangeSum = "{$column}{$firstDataRow}:{$column}{$lastDataRow}";
                        $sheet->setCellValue("{$column}{$totalNonKSGRow}", "=SUMIF({$rangeCategory}, \"<>*KSG*\", {$rangeSum})");
                        $sheet->getStyle("{$column}{$totalNonKSGRow}")->getNumberFormat()->setFormatCode("Rp #,##0_);(Rp #,##0)");
                    }

                    $totalRowStyle = [
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFE0B2']],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                    ];
                    
                    $sheet->getStyle("A{$totalRow}:{$lastColLetter}{$totalRow}")->applyFromArray($totalRowStyle);
                    $sheet->getStyle("A{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    
                    $sheet->getStyle("A{$totalNonKSGRow}:{$lastColLetter}{$totalNonKSGRow}")->applyFromArray(array_merge($totalRowStyle, [
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFC6EFCE']],
                    ]));
                    $sheet->getStyle("A{$totalNonKSGRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                }

                $sheet->freezePane('A' . ($this->headerRowCount + 1));
            },
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastDataRow = $this->totalRows + $this->headerRowCount;
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($this->headings()));

        return [
            "A" . ($this->headerRowCount + 1) . ":{$lastColLetter}{$lastDataRow}" => [
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP],
            ],
            "T" . ($this->headerRowCount + 1) . ":Z{$lastDataRow}" => [
                 'alignment' => [
                    'wrapText' => true,
                    'vertical' => Alignment::VERTICAL_TOP
                 ],
            ],
        ];
    }
}