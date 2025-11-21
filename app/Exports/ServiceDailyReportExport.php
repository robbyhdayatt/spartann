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
    protected $startDate; // Diubah dari filterDate
    protected $endDate;   // Ditambahkan
    protected $dealers;
    private $totalRows = 0;
    private $headerRowCount = 2;

    // Constructor menerima start dan end date
    public function __construct($dealerCode, $startDate, $endDate)
    {
        $this->dealerCode = $dealerCode;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->dealers = Lokasi::where('tipe', 'DEALER')->pluck('nama_lokasi', 'kode_lokasi');
    }

    public function bindValue(Cell $cell, $value)
    {
        if (in_array($cell->getColumn(), ['L', 'O', 'R', 'V'])) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }

        // Format Rupiah untuk kolom nominal
        if (in_array($cell->getColumn(), ['AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AM', 'AN'])) {
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

        // Filter Berdasarkan Rentang Tanggal (Start - End)
        if ($this->startDate && $this->endDate) {
            $start = Carbon::parse($this->startDate)->startOfDay();
            $end = Carbon::parse($this->endDate)->endOfDay();

            $query->whereBetween('services.created_at', [$start, $end]);
        } elseif ($this->startDate) {
            // Fallback jika hanya ada start date
            $query->whereDate('services.created_at', '>=', $this->startDate);
        }

        // Filter Dealer (Jika 'all', maka filter ini dilewati)
        if ($this->dealerCode && $this->dealerCode !== 'all') {
            $query->where('dealer_code', $this->dealerCode);
        }

        $this->totalRows = (clone $query)->count();
        return $query;
    }

    /**
     * @var Service $service
     */
    public function map($service): array
    {
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
            $service->e_payment_amount,     // AB
            $service->cash_amount,          // AC
            $service->debit_amount,         // AD
            $service->total_down_payment,   // AE
            $service->total_labor,          // AF
            $service->total_part_service,   // AG
            $service->total_oil_service,    // AH
            $service->total_retail_parts,   // AI
            $service->total_retail_oil,     // AJ
            $service->total_amount,         // AK
            $service->benefit_amount,       // AL
            $service->total_payment,        // AM
            $service->balance,              // AN
            $service->technician_name,      // AO
            $service->printed_at ? Carbon::parse($service->printed_at)->format('Y-m-d H:i:s') : null, // AP
            $service->created_at ? Carbon::parse($service->created_at)->format('Y-m-d H:i:s') : null, // AQ
        ];
    }

     /**
     * Helper function to format number as Rupiah for Excel display within concatenated strings.
     */
    private function formatRupiahExcel($number)
    {
        if (!is_numeric($number)) {
            return $number;
        }
        return 'Rp ' . number_format($number, 0, ',', '.');
    }

    public function headings(): array
    {
        return [
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

                // Tambah baris untuk Group Header
                $sheet->insertNewRowBefore(1, 1);
                $groupHeaderRow = 1;
                $headerRow = 2;

                $headings = $this->headings();
                $numHeadings = count($headings);
                $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($numHeadings);

                // Definisi Group Header
                $groupHeaders = [
                    'K' => ['range' => "K{$groupHeaderRow}:O{$groupHeaderRow}", 'title' => 'Customer Information'],
                    'P' => ['range' => "P{$groupHeaderRow}:R{$groupHeaderRow}", 'title' => 'M/C Information'],
                    'S' => ['range' => "S{$groupHeaderRow}:Y{$groupHeaderRow}", 'title' => 'Service Details'],
                    'AE' => ['range' => "AE{$groupHeaderRow}:AJ{$groupHeaderRow}", 'title' => 'Amount Breakdown'],
                    'AK' => ['range' => "AK{$groupHeaderRow}:AN{$groupHeaderRow}", 'title' => 'Totals & Balance']
                ];

                $groupedColumns = [];
                foreach ($groupHeaders as $startCol => $group) {
                    $sheet->mergeCells($group['range']);
                    $sheet->setCellValue("{$startCol}{$groupHeaderRow}", $group['title']);
                    list($rangeStart, $rangeEnd) = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::rangeBoundaries($group['range']);
                    for ($colIdx = $rangeStart[0]; $colIdx <= $rangeEnd[0]; $colIdx++) {
                         $groupedColumns[$colIdx] = true;
                    }
                }

                // Isi header yang tidak tergrup
                for ($colIndex = 1; $colIndex <= $numHeadings; $colIndex++) {
                    if (!isset($groupedColumns[$colIndex])) {
                        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                        $headerValue = $sheet->getCell("{$colLetter}{$headerRow}")->getValue();
                        $sheet->setCellValue("{$colLetter}{$groupHeaderRow}", $headerValue);
                        $sheet->mergeCells("{$colLetter}{$groupHeaderRow}:{$colLetter}{$headerRow}");
                    }
                }

                // Styling Header
                $headerStyle = [
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4F81BD']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                ];
                $sheet->getStyle("A{$groupHeaderRow}:{$lastColLetter}{$headerRow}")->applyFromArray($headerStyle);
                $sheet->getRowDimension($groupHeaderRow)->setRowHeight(25);
                $sheet->getRowDimension($headerRow)->setRowHeight(40);

                // Styling dan Rumus Total
                if ($this->totalRows > 0) {
                    $firstDataRow = $this->headerRowCount + 1;
                    $lastDataRow = $this->totalRows + $this->headerRowCount;

                    $totalRow = $lastDataRow + 1;
                    $totalNonKSGRow = $lastDataRow + 2; // Baris Baru untuk Total Tanpa KSG

                    $columnsToSum = ['AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AM', 'AN'];
                    $mergeUntilCol = 'AA';

                    // --- BARIS 1: TOTAL SEMUA ---
                    $sheet->setCellValue("A{$totalRow}", 'TOTAL SEMUA');
                    $sheet->mergeCells("A{$totalRow}:{$mergeUntilCol}{$totalRow}");

                    foreach ($columnsToSum as $column) {
                        $sheet->setCellValue("{$column}{$totalRow}", "=SUM({$column}{$firstDataRow}:{$column}{$lastDataRow})");
                        $sheet->getStyle("{$column}{$totalRow}")->getNumberFormat()->setFormatCode("Rp #,##0_);(Rp #,##0)");
                    }

                    // --- BARIS 2: TOTAL TANPA KSG (SUMIF) ---
                    $sheet->setCellValue("A{$totalNonKSGRow}", 'TOTAL (TANPA KSG)');
                    $sheet->mergeCells("A{$totalNonKSGRow}:{$mergeUntilCol}{$totalNonKSGRow}");

                    foreach ($columnsToSum as $column) {
                        // Rumus: =SUMIF(S2:S100, "<>*KSG*", AB2:AB100)
                        // S adalah kolom 'Service Category'
                        $rangeCategory = "S{$firstDataRow}:S{$lastDataRow}";
                        $rangeSum = "{$column}{$firstDataRow}:{$column}{$lastDataRow}";

                        // Kriteria: "<>*KSG*" artinya tidak mengandung kata KSG
                        $sheet->setCellValue("{$column}{$totalNonKSGRow}", "=SUMIF({$rangeCategory}, \"<>*KSG*\", {$rangeSum})");
                        $sheet->getStyle("{$column}{$totalNonKSGRow}")->getNumberFormat()->setFormatCode("Rp #,##0_);(Rp #,##0)");
                    }

                    // Styling Footer (Total)
                    $totalRowStyle = [
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFE0B2']], // Warna Orange Muda
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                    ];

                    // Terapkan style ke baris Total Semua
                    $sheet->getStyle("A{$totalRow}:{$lastColLetter}{$totalRow}")->applyFromArray($totalRowStyle);
                    $sheet->getStyle("A{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                    // Terapkan style ke baris Total Tanpa KSG (Warna beda dikit biar kontras)
                    $sheet->getStyle("A{$totalNonKSGRow}:{$lastColLetter}{$totalNonKSGRow}")->applyFromArray(array_merge($totalRowStyle, [
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFC6EFCE']], // Warna Hijau Muda
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

        $stylesArray = [
            "A" . ($this->headerRowCount + 1) . ":{$lastColLetter}{$lastDataRow}" => [
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP],
            ],
            "S" . ($this->headerRowCount + 1) . ":Y{$lastDataRow}" => [
                 'alignment' => [
                    'wrapText' => true,
                    'vertical' => Alignment::VERTICAL_TOP
                 ],
            ],
        ];

        return $stylesArray;
    }
}
