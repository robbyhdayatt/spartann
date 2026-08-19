<?php

namespace App\Exports;

use App\Models\Service;
use App\Models\Lokasi;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
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
use Carbon\Carbon;

class ServiceDailyReportExport extends DefaultValueBinder implements
    FromCollection,
    WithHeadings,
    WithEvents,
    WithStyles,
    WithCustomValueBinder
{
    protected $dealerCode;
    protected $startDate;
    protected $endDate;
    protected $dealers;

    private $totalDataRows = 0;
    private $mergeRanges = [];

    public function __construct($dealerCode, $startDate, $endDate)
    {
        $this->dealerCode = $dealerCode;
        $this->startDate  = $startDate;
        $this->endDate    = $endDate;
        $this->dealers    = Lokasi::where('tipe', 'DEALER')->pluck('nama_lokasi', 'kode_lokasi');
    }

    public function bindValue(Cell $cell, $value)
    {
        // Memaksa kolom KTP (N), Telepon (M), NPWP (O), No Rangka (R), No Invoice (B), Kode Part (W) bertipe STRING murni
        // Mencegah Excel mengubah angka 16-digit KTP menjadi notasi ilmiah (e.g., 1.87107E+15)
        $stringColumns = ['B', 'C', 'D', 'F', 'G', 'H', 'I', 'J', 'K', 'M', 'N', 'O', 'R', 'W'];
        if (in_array($cell->getColumn(), $stringColumns)) {
            $cell->setValueExplicit((string) ($value ?? '-'), DataType::TYPE_STRING);
            return true;
        }

        return parent::bindValue($cell, $value);
    }

    private function applyFilters($query)
    {
        if ($this->startDate && $this->endDate) {
            $start = Carbon::parse($this->startDate)->startOfDay();
            $end   = Carbon::parse($this->endDate)->endOfDay();
            $query->whereBetween('services.created_at', [$start, $end]);
        } elseif ($this->startDate) {
            $start = Carbon::parse($this->startDate)->startOfDay();
            $query->where('services.created_at', '>=', $start);
        }

        if ($this->dealerCode !== 'all' && $this->dealerCode !== null && $this->dealerCode !== '') {
            $query->where('services.dealer_code', $this->dealerCode);
        }

        return $query;
    }

    private function getBaseQuery()
    {
        $query = Service::query();
        return $this->applyFilters($query);
    }

    public function collection()
    {
        $query = Service::with(['lokasi:id,kode_lokasi,nama_lokasi', 'details']);
        $query = $this->applyFilters($query);

        $services = $query->orderBy('services.created_at', 'desc')
            ->orderBy('services.id', 'desc')
            ->get();

        $rows = [];
        $invoiceNumber = 1;
        $currentRow = 2; // Data dimulai di baris 2

        foreach ($services as $service) {
            $namaDealer = $this->dealers->get($service->dealer_code) ?? ($service->lokasi->nama_lokasi ?? $service->dealer_code);
            $details = $service->details;
            $detailCount = $details->count();

            $startRow = $currentRow;

            if ($detailCount > 0) {
                foreach ($details as $detail) {
                    $rows[] = [
                        $invoiceNumber,
                        (string) ($service->invoice_no ?? '-'),
                        $service->reg_date ? Carbon::parse($service->reg_date)->format('Y-m-d') : '-',
                        (string) ($service->dealer_code ?? '-'),
                        $namaDealer,
                        (string) ($service->yss ?? '-'),
                        (string) ($service->point ?? '-'),
                        $service->service_order ?? '-',
                        (string) ($service->plate_no ?? '-'),
                        (string) ($service->work_order_no ?? '-'),
                        (string) ($service->work_order_status ?? '-'),
                        $service->customer_name ?? '-',
                        (string) ($service->customer_phone ?? '-'),
                        (string) ($service->customer_ktp ?? '-'),
                        (string) ($service->customer_npwp_no ?? '-'),
                        $service->mc_brand ?? '-',
                        $service->mc_model_name ?? '-',
                        (string) ($service->mc_frame_no ?? '-'),
                        // --- ITEM DETAILS (S-AB) ---
                        $detail->item_category ?? '-',
                        $detail->service_category_code ?? '-',
                        $detail->service_package_name ?? '-',
                        (float) ($detail->labor_cost_service ?? 0),
                        (string) ($detail->item_code ?? '-'),
                        $detail->item_name ?? '-',
                        (int) ($detail->quantity ?? 0),
                        (float) ($detail->price ?? 0),
                        (float) (($detail->quantity ?? 0) * ($detail->price ?? 0)),
                        (float) ($detail->cost_price ?? 0),
                        // --- INVOICE FINANCIALS (AC-AT) ---
                        $service->payment_type ?? '-',
                        $service->transaction_code ?? '-',
                        (float) ($service->e_payment_amount ?? 0),
                        (float) ($service->cash_amount ?? 0),
                        (float) ($service->debit_amount ?? 0),
                        (float) ($service->total_down_payment ?? 0),
                        (float) ($service->total_labor ?? 0),
                        (float) ($service->total_part_service ?? 0),
                        (float) ($service->total_oil_service ?? 0),
                        (float) ($service->total_retail_parts ?? 0),
                        (float) ($service->total_retail_oil ?? 0),
                        (float) ($service->total_amount ?? 0),
                        (float) ($service->benefit_amount ?? 0),
                        (float) ($service->total_payment ?? 0),
                        (float) ($service->balance ?? 0),
                        $service->technician_name ?? '-',
                        $service->printed_at ? Carbon::parse($service->printed_at)->format('Y-m-d H:i:s') : '-',
                        $service->created_at ? Carbon::parse($service->created_at)->format('Y-m-d H:i:s') : '-',
                    ];

                    $currentRow++;
                }
            } else {
                $rows[] = [
                    $invoiceNumber,
                    (string) ($service->invoice_no ?? '-'),
                    $service->reg_date ? Carbon::parse($service->reg_date)->format('Y-m-d') : '-',
                    (string) ($service->dealer_code ?? '-'),
                    $namaDealer,
                    (string) ($service->yss ?? '-'),
                    (string) ($service->point ?? '-'),
                    $service->service_order ?? '-',
                    (string) ($service->plate_no ?? '-'),
                    (string) ($service->work_order_no ?? '-'),
                    (string) ($service->work_order_status ?? '-'),
                    $service->customer_name ?? '-',
                    (string) ($service->customer_phone ?? '-'),
                    (string) ($service->customer_ktp ?? '-'),
                    (string) ($service->customer_npwp_no ?? '-'),
                    $service->mc_brand ?? '-',
                    $service->mc_model_name ?? '-',
                    (string) ($service->mc_frame_no ?? '-'),
                    '-', '-', '-', 0, '-', '-', 0, 0, 0, 0,
                    $service->payment_type ?? '-',
                    $service->transaction_code ?? '-',
                    (float) ($service->e_payment_amount ?? 0),
                    (float) ($service->cash_amount ?? 0),
                    (float) ($service->debit_amount ?? 0),
                    (float) ($service->total_down_payment ?? 0),
                    (float) ($service->total_labor ?? 0),
                    (float) ($service->total_part_service ?? 0),
                    (float) ($service->total_oil_service ?? 0),
                    (float) ($service->total_retail_parts ?? 0),
                    (float) ($service->total_retail_oil ?? 0),
                    (float) ($service->total_amount ?? 0),
                    (float) ($service->benefit_amount ?? 0),
                    (float) ($service->total_payment ?? 0),
                    (float) ($service->balance ?? 0),
                    $service->technician_name ?? '-',
                    $service->printed_at ? Carbon::parse($service->printed_at)->format('Y-m-d H:i:s') : '-',
                    $service->created_at ? Carbon::parse($service->created_at)->format('Y-m-d H:i:s') : '-',
                ];
                $currentRow++;
            }

            $endRow = $currentRow - 1;

            if ($startRow < $endRow) {
                // Merge Header Info (A-R)
                for ($col = 'A'; $col !== 'S'; $col++) {
                    $this->mergeRanges[] = "{$col}{$startRow}:{$col}{$endRow}";
                }

                // Merge Kategori & Paket Service jika seragam per invoice
                $firstCategory = $details->first()->service_category_code ?? null;
                $allSameCategory = $details->every(function ($d) use ($firstCategory) {
                    return ($d->service_category_code ?? null) === $firstCategory;
                });
                if ($allSameCategory && $firstCategory !== null) {
                    $this->mergeRanges[] = "T{$startRow}:T{$endRow}";
                }

                $firstPackage = $details->first()->service_package_name ?? null;
                $allSamePackage = $details->every(function ($d) use ($firstPackage) {
                    return ($d->service_package_name ?? null) === $firstPackage;
                });
                if ($allSamePackage && $firstPackage !== null) {
                    $this->mergeRanges[] = "U{$startRow}:U{$endRow}";
                }

                // Merge Total Keuangan (AC-AT)
                $columnsACtoAT = ['AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT'];
                foreach ($columnsACtoAT as $col) {
                    $this->mergeRanges[] = "{$col}{$startRow}:{$col}{$endRow}";
                }
            }

            $invoiceNumber++;
        }

        $this->totalDataRows = count($rows);
        return collect($rows);
    }

    public function headings(): array
    {
        return [
            'No.',
            'No Invoice',
            'Tanggal Service',
            'Kode Dealer',
            'Nama Dealer',
            'YSS',
            'Point',
            'Service Order',
            'No Polisi',
            'No Work Order',
            'Status Work Order',
            'Nama Pelanggan',
            'Telepon Pelanggan',
            'KTP Pelanggan',
            'NPWP Pelanggan',
            'Brand Motor',
            'Model Motor',
            'No Rangka Motor',
            // --- DETAIL ITEM ---
            'Kategori Item',
            'Kategori Service',
            'Paket Service',
            'Labor Cost Service',
            'Kode Part / Item',
            'Nama Part / Item',
            'Qty Item',
            'Harga Satuan',
            'Subtotal Item',
            'HPP Satuan',
            // --- INVOICE FINANCIALS ---
            'Tipe Pembayaran',
            'Kode Transaksi',
            'E-Payment',
            'Cash',
            'Debit',
            'Total DP',
            'Total Labor',
            'Total Part Service',
            'Total Oil Service',
            'Total Retail Parts',
            'Total Retail Oil',
            'Total Amount (Gross)',
            'Benefit Amount',
            'Total Payment (Net)',
            'Balance',
            'Nama Teknisi',
            'Waktu Cetak',
            'Tanggal Import',
        ];
    }

    private function calculateTotalWithoutKSG()
    {
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

        $ksgLaborSum = DB::table('service_details')
            ->join('services', 'service_details.service_id', '=', 'services.id')
            ->where('services.balance', '<', 0)
            ->when($this->startDate && $this->endDate, function ($q) {
                $start = Carbon::parse($this->startDate)->startOfDay();
                $end   = Carbon::parse($this->endDate)->endOfDay();
                $q->whereBetween('services.created_at', [$start, $end]);
            }, function ($q) {
                if ($this->startDate) {
                    $q->where('services.created_at', '>=', Carbon::parse($this->startDate)->startOfDay());
                }
            })
            ->when($this->dealerCode !== 'all' && $this->dealerCode !== null && $this->dealerCode !== '', function ($q) {
                $q->where('services.dealer_code', $this->dealerCode);
            })
            ->where(function ($q) {
                $q->where('service_details.service_package_name', 'LIKE', '%KSG%')
                  ->orWhere('service_details.service_package_name', 'LIKE', '%CLAIM%')
                  ->orWhere('service_details.service_category_code', 'LIKE', '%KSG%')
                  ->orWhere('service_details.service_category_code', 'LIKE', '%CLAIM%');
            })
            ->sum(DB::raw("
                CASE 
                    WHEN service_details.labor_cost_service > 0 THEN service_details.labor_cost_service
                    WHEN UPPER(service_details.service_package_name) LIKE '%KSG1%' OR UPPER(service_details.service_category_code) LIKE '%KSG1%' THEN
                        CASE WHEN UPPER(services.mc_model_name) LIKE '%MX KING%' THEN 28000 ELSE 24000 END
                    WHEN UPPER(service_details.service_package_name) LIKE '%KSG2%' OR UPPER(service_details.service_category_code) LIKE '%KSG2%' THEN 25000
                    WHEN UPPER(service_details.service_package_name) LIKE '%KSG3%' OR UPPER(service_details.service_category_code) LIKE '%KSG3%' THEN 25000
                    WHEN UPPER(service_details.service_package_name) LIKE '%KSG4%' OR UPPER(service_details.service_category_code) LIKE '%KSG4%' THEN
                        CASE WHEN UPPER(services.mc_model_name) LIKE '%NEO%' THEN 42000 ELSE 29000 END
                    WHEN UPPER(service_details.service_package_name) LIKE '%CLAIM%' OR UPPER(service_details.service_category_code) LIKE '%CLAIM%' THEN 16000
                    ELSE 0
                END
            "));

        return (object) [
            'e_payment_amount'   => $grandTotal->e_payment_amount ?? 0,
            'cash_amount'        => $grandTotal->cash_amount ?? 0,
            'debit_amount'       => $grandTotal->debit_amount ?? 0,
            'total_down_payment' => $grandTotal->total_down_payment ?? 0,
            'total_labor'        => ($grandTotal->total_labor ?? 0) - $ksgLaborSum,
            'total_part_service' => $grandTotal->total_part_service ?? 0,
            'total_oil_service'  => $grandTotal->total_oil_service ?? 0,
            'total_retail_parts' => $grandTotal->total_retail_parts ?? 0,
            'total_retail_oil'   => $grandTotal->total_retail_oil ?? 0,
            'total_amount'       => ($grandTotal->total_amount ?? 0) - $ksgLaborSum,
            'benefit_amount'     => $grandTotal->benefit_amount ?? 0,
            'total_payment'      => $grandTotal->total_payment ?? 0,
            'balance'            => ($grandTotal->total_payment ?? 0) - (($grandTotal->total_amount ?? 0) - $ksgLaborSum),
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $this->totalDataRows + 1; // +1 untuk header
                $lastColLetter = 'AT'; // Kolom 46

                // Set default Font Size ke 9pt
                $sheet->getParent()->getDefaultStyle()->getFont()->setSize(9);

                // 1. Eksekusi merge vertikal per invoice
                foreach ($this->mergeRanges as $range) {
                    $sheet->mergeCells($range);
                }

                // 2. Format Massal Kolom Keuangan & Text di C-level kecepatan PhpSpreadsheet
                if ($this->totalDataRows > 0) {
                    $rupiahFormat = '"Rp "#,##0_);("Rp "#,##0)';

                    // Format Text (@) eksplisit untuk NIK KTP, Telepon, NPWP, No Rangka, No Invoice
                    $sheet->getStyle("B2:K{$lastRow}")->getNumberFormat()->setFormatCode('@');
                    $sheet->getStyle("M2:O{$lastRow}")->getNumberFormat()->setFormatCode('@');
                    $sheet->getStyle("R2:R{$lastRow}")->getNumberFormat()->setFormatCode('@');
                    $sheet->getStyle("W2:W{$lastRow}")->getNumberFormat()->setFormatCode('@');

                    // Format Rupiah untuk kolom nominal V, Z, AA, AB, AE s/d AQ
                    $sheet->getStyle("V2:V{$lastRow}")->getNumberFormat()->setFormatCode($rupiahFormat);
                    $sheet->getStyle("Z2:AB{$lastRow}")->getNumberFormat()->setFormatCode($rupiahFormat);
                    $sheet->getStyle("AE2:AQ{$lastRow}")->getNumberFormat()->setFormatCode($rupiahFormat);

                    // Format Qty Integer untuk kolom Y
                    $sheet->getStyle("Y2:Y{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
                }

                // 3. AutoFilter & Freeze Pane
                $sheet->setAutoFilter("A1:{$lastColLetter}1");
                $sheet->freezePane('A2');

                // 4. Tambahkan Baris TOTAL SUMMARY dan TOTAL (TANPA KSG) di bagian bawah
                if ($this->totalDataRows > 0) {
                    $totalRow = $lastRow + 1;
                    $totalNonKsgRow = $lastRow + 2;

                    // --- BARIS 1: TOTAL SUMMARY ---
                    $sheet->setCellValue("A{$totalRow}", 'TOTAL SUMMARY');

                    $sumColumns = [
                        'Y'  => 'Qty Item',
                        'AA' => 'Subtotal Item',
                        'AE' => 'E-Payment',
                        'AF' => 'Cash',
                        'AG' => 'Debit',
                        'AH' => 'Total DP',
                        'AI' => 'Total Labor',
                        'AJ' => 'Total Part Service',
                        'AK' => 'Total Oil Service',
                        'AL' => 'Total Retail Parts',
                        'AM' => 'Total Retail Oil',
                        'AN' => 'Total Amount',
                        'AO' => 'Benefit Amount',
                        'AP' => 'Total Payment',
                        'AQ' => 'Balance',
                    ];

                    foreach ($sumColumns as $col => $name) {
                        $sheet->setCellValue("{$col}{$totalRow}", "=SUM({$col}2:{$col}{$lastRow})");
                        $format = ($col === 'Y') ? '#,##0' : '"Rp "#,##0_);("Rp "#,##0)';
                        $sheet->getStyle("{$col}{$totalRow}")->getNumberFormat()->setFormatCode($format);
                    }

                    $totalStyle = [
                        'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '000000']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE2EFDA']],
                        'borders' => [
                            'top' => ['borderStyle' => Border::BORDER_THIN],
                            'bottom' => ['borderStyle' => Border::BORDER_THIN],
                        ],
                    ];
                    $sheet->getStyle("A{$totalRow}:{$lastColLetter}{$totalRow}")->applyFromArray($totalStyle);

                    // --- BARIS 2: TOTAL (TANPA KSG) ---
                    $nonKsgTotal = $this->calculateTotalWithoutKSG();
                    $sheet->setCellValue("A{$totalNonKsgRow}", 'TOTAL (TANPA KSG)');

                    $colMap = [
                        'AE' => 'e_payment_amount',
                        'AF' => 'cash_amount',
                        'AG' => 'debit_amount',
                        'AH' => 'total_down_payment',
                        'AI' => 'total_labor',
                        'AJ' => 'total_part_service',
                        'AK' => 'total_oil_service',
                        'AL' => 'total_retail_parts',
                        'AM' => 'total_retail_oil',
                        'AN' => 'total_amount',
                        'AO' => 'benefit_amount',
                        'AP' => 'total_payment',
                        'AQ' => 'balance',
                    ];

                    $sheet->setCellValue("Y{$totalNonKsgRow}", "=SUM(Y2:Y{$lastRow})");
                    $sheet->getStyle("Y{$totalNonKsgRow}")->getNumberFormat()->setFormatCode('#,##0');

                    foreach ($colMap as $col => $key) {
                        $val = $nonKsgTotal->$key ?? 0;
                        $sheet->setCellValue("{$col}{$totalNonKsgRow}", $val);
                        $sheet->getStyle("{$col}{$totalNonKsgRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0_);("Rp "#,##0)');
                    }

                    $nonKsgStyle = [
                        'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '006100']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFC6EFCE']],
                        'borders' => [
                            'top' => ['borderStyle' => Border::BORDER_THIN],
                            'bottom' => ['borderStyle' => Border::BORDER_DOUBLE],
                        ],
                    ];
                    $sheet->getStyle("A{$totalNonKsgRow}:{$lastColLetter}{$totalNonKsgRow}")->applyFromArray($nonKsgStyle);
                }
            },
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $this->totalDataRows + 1;

        return [
            // Header Baris 1: Font Size 9 Bold Navy
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size' => 9,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF1F4E78'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => false,
                ],
            ],
            // Data Rows: Font Size 9, Vertical Alignment Center
            "A2:AT{$lastRow}" => [
                'font' => [
                    'size' => 9,
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }
}