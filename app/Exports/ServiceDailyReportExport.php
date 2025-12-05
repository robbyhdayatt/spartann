<?php

namespace App\Exports;

use App\Models\Service;
use App\Models\ServiceDetail;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        if (in_array($cell->getColumn(), ['M', 'P', 'S', 'W'])) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        
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

    private function applyFilters($query) {
        if ($this->startDate && $this->endDate) {
            $start = Carbon::parse($this->startDate)->startOfDay();
            $end = Carbon::parse($this->endDate)->endOfDay();
            $query->whereBetween('created_at', [$start, $end]);
        } elseif ($this->startDate) {
            $start = Carbon::parse($this->startDate)->startOfDay();
            $query->where('created_at', '>=', $start);
        }

        if ($this->dealerCode && $this->dealerCode !== 'all') {
            $query->where('dealer_code', $this->dealerCode);
        }
        return $query;
    }

    private function getBaseQuery(): Builder
    {
        $query = Service::query();
        return $this->applyFilters($query);
    }

    public function query(): Builder
    {
        $query = $this->getBaseQuery()
                 ->with(['lokasi', 'details'])
                 ->orderBy('created_at', 'desc');

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

    /**
     * PERBAIKAN FINAL (AKURASI TINGGI):
     * 1. Filter: Hanya hitung item KSG jika Service Balance < 0 (Artinya ada subsidi/claim).
     * 2. Mapping: Harga dinamis berdasarkan Model Motor (MX KING & NEO).
     */
    private function calculateTotalWithoutKSG()
    {
        // 1. Ambil Total Keseluruhan
        $grandTotal = (clone $this->getBaseQuery())->selectRaw('
            SUM(e_payment_amount) as e_payment_amount,
            SUM(cash_amount) as cash_amount,
            SUM(debit_amount) as debit_amount,
            SUM(total_down_payment) as total_down_payment,
            SUM(total_labor) as total_labor,
            SUM(total_part_service) as total_part_service,
            SUM(total_oil_service) as total_oil_service,
            SUM(total_retail_parts) as total_retail_parts,
            SUM(total_retail_oil) as total_retail_oil,
            SUM(total_amount) as total_amount,
            SUM(benefit_amount) as benefit_amount,
            SUM(total_payment) as total_payment,
            SUM(balance) as balance
        ')->first();

        // 2. Ambil detail TAPI HANYA dari Service yang Balance-nya NEGATIF (< 0)
        // Ini kunci untuk membuang baris yang nilainya 0 (tidak valid/rejected)
        $details = ServiceDetail::with('service')
            ->whereHas('service', function($q) {
                $this->applyFilters($q);
                $q->where('balance', '<', 0); 
            })->get();

        $ksgLaborSum = 0;

        foreach ($details as $item) {
            $pkgName = strtoupper($item->service_package_name ?? '');
            $laborCost = $item->labor_cost_service;

            // Cek apakah Item ini KSG atau CLAIM
            if (str_contains($pkgName, 'KSG') || str_contains($pkgName, 'CLAIM')) {
                
                // Jika di database ada harganya, pakai itu.
                if ($laborCost > 0) {
                    $ksgLaborSum += $laborCost;
                } 
                // Jika 0, pakai Mapping Manual (Spesifik per Motor)
                else {
                    $modelName = strtoupper($item->service->mc_model_name ?? '');

                    if (str_contains($pkgName, 'KSG1')) {
                        // Cek Model Khusus
                        if (str_contains($modelName, 'MX KING')) {
                            $ksgLaborSum += 28000;
                        } else {
                            $ksgLaborSum += 24000;
                        }
                    } 
                    elseif (str_contains($pkgName, 'KSG2')) {
                        $ksgLaborSum += 25000;
                    } 
                    elseif (str_contains($pkgName, 'KSG3')) {
                        $ksgLaborSum += 25000;
                    } 
                    elseif (str_contains($pkgName, 'KSG4')) {
                        // Cek Model Khusus
                        if (str_contains($modelName, 'NEO')) { // NMAX NEO
                            $ksgLaborSum += 42000;
                        } else {
                            $ksgLaborSum += 29000;
                        }
                    } 
                    elseif (str_contains($pkgName, 'CLAIM')) {
                        $ksgLaborSum += 16000;
                    }
                }
            }
        }

        // 3. Return Object Data
        return (object) [
            'e_payment_amount' => $grandTotal->e_payment_amount ?? 0,
            'cash_amount' => $grandTotal->cash_amount ?? 0,
            'debit_amount' => $grandTotal->debit_amount ?? 0,
            'total_down_payment' => $grandTotal->total_down_payment ?? 0,
            'total_labor' => ($grandTotal->total_labor ?? 0) - $ksgLaborSum,
            'total_part_service' => $grandTotal->total_part_service ?? 0, 
            'total_oil_service' => $grandTotal->total_oil_service ?? 0, 
            'total_retail_parts' => $grandTotal->total_retail_parts ?? 0,
            'total_retail_oil' => $grandTotal->total_retail_oil ?? 0,
            'total_amount' => ($grandTotal->total_amount ?? 0) - $ksgLaborSum, 
            'benefit_amount' => $grandTotal->benefit_amount ?? 0,
            'total_payment' => $grandTotal->total_payment ?? 0, 
            'balance' => ($grandTotal->total_payment ?? 0) - (($grandTotal->total_amount ?? 0) - $ksgLaborSum)
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                
                $grandTotalQuery = (clone $this->getBaseQuery())->selectRaw('
                    SUM(e_payment_amount) as e_payment_amount,
                    SUM(cash_amount) as cash_amount,
                    SUM(debit_amount) as debit_amount,
                    SUM(total_down_payment) as total_down_payment,
                    SUM(total_labor) as total_labor,
                    SUM(total_part_service) as total_part_service,
                    SUM(total_oil_service) as total_oil_service,
                    SUM(total_retail_parts) as total_retail_parts,
                    SUM(total_retail_oil) as total_retail_oil,
                    SUM(total_amount) as total_amount,
                    SUM(benefit_amount) as benefit_amount,
                    SUM(total_payment) as total_payment,
                    SUM(balance) as balance
                ')->first();

                $nonKsgTotal = $this->calculateTotalWithoutKSG();

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
                    $lastDataRow = $this->totalRows + $this->headerRowCount;
                    $totalRow = $lastDataRow + 1;
                    $totalNonKSGRow = $lastDataRow + 2;

                    $colMap = [
                        'AC' => 'e_payment_amount',
                        'AD' => 'cash_amount',
                        'AE' => 'debit_amount',
                        'AF' => 'total_down_payment',
                        'AG' => 'total_labor',
                        'AH' => 'total_part_service',
                        'AI' => 'total_oil_service',
                        'AJ' => 'total_retail_parts',
                        'AK' => 'total_retail_oil',
                        'AL' => 'total_amount',
                        'AM' => 'benefit_amount',
                        'AN' => 'total_payment',
                        'AO' => 'balance'
                    ];
                    $mergeUntilCol = 'AB';

                    $sheet->setCellValue("A{$totalRow}", 'TOTAL SEMUA');
                    $sheet->mergeCells("A{$totalRow}:{$mergeUntilCol}{$totalRow}");
                    
                    foreach ($colMap as $col => $key) {
                        $val = $grandTotalQuery->$key ?? 0;
                        $sheet->setCellValue("{$col}{$totalRow}", $val);
                        $sheet->getStyle("{$col}{$totalRow}")->getNumberFormat()->setFormatCode("Rp #,##0_);(Rp #,##0)");
                    }

                    $sheet->setCellValue("A{$totalNonKSGRow}", 'TOTAL (TANPA KSG)');
                    $sheet->mergeCells("A{$totalNonKSGRow}:{$mergeUntilCol}{$totalNonKSGRow}");
                    
                    foreach ($colMap as $col => $key) {
                        $val = $nonKsgTotal->$key ?? 0;
                        $sheet->setCellValue("{$col}{$totalNonKSGRow}", $val);
                        $sheet->getStyle("{$col}{$totalNonKSGRow}")->getNumberFormat()->setFormatCode("Rp #,##0_);(Rp #,##0)");
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