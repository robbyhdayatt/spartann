<?php

namespace App\Exports;

use App\Models\Service;
use App\Models\ServiceDetail;
use App\Models\Lokasi;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
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
use Carbon\Carbon;

class ServiceDailyReportExport extends DefaultValueBinder implements
    FromCollection,
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

    private $totalDataRows = 0;
    private $mergeRanges = [];

    public function __construct($dealerCode, $startDate, $endDate)
    {
        $this->dealerCode = $dealerCode;
        $this->startDate  = $startDate;
        $this->endDate    = $endDate;
        $this->dealers    = Lokasi::where('tipe', 'DEALER')->pluck('nama_lokasi', 'kode_lokasi');
    }

    private function applyFilters($query)
    {
        if ($this->startDate && $this->endDate) {
            $start = Carbon::parse($this->startDate)->startOfDay();
            $end   = Carbon::parse($this->endDate)->endOfDay();
            $query->whereBetween('created_at', [$start, $end]);
        } elseif ($this->startDate) {
            $start = Carbon::parse($this->startDate)->startOfDay();
            $query->where('created_at', '>=', $start);
        }

        if ($this->dealerCode !== 'all' && $this->dealerCode !== null && $this->dealerCode !== '') {
            $query->where('dealer_code', $this->dealerCode);
        }

        return $query;
    }

    private function getBaseQuery()
    {
        $query = Service::query();
        return $this->applyFilters($query);
    }

    public function bindValue(Cell $cell, $value)
    {
        // Kolom kode/nomor yang wajib diawali string agar 0 di depan tidak terpotong
        $stringColumns = ['C', 'G', 'I', 'J', 'K', 'M', 'N', 'O', 'R', 'W'];
        if (in_array($cell->getColumn(), $stringColumns)) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
            return true;
        }

        // Kolom Keuangan/Nominal Rupiah
        $moneyColumns = [
            'V',  // Labor Cost Service
            'Z',  // Harga Satuan
            'AA', // Subtotal Item
            'AB', // HPP Satuan
            'AE', // E-Payment
            'AF', // Cash
            'AG', // Debit
            'AH', // Total DP
            'AI', // Total Labor
            'AJ', // Total Part Service
            'AK', // Total Oil Service
            'AL', // Total Retail Parts
            'AM', // Total Retail Oil
            'AN', // Total Amount (Gross)
            'AO', // Benefit Amount
            'AP', // Total Payment (Net)
            'AQ'  // Balance
        ];

        if (in_array($cell->getColumn(), $moneyColumns) && is_numeric($value)) {
            $cell->setValueExplicit((float) $value, DataType::TYPE_NUMERIC);
            $cell->getStyle()->getNumberFormat()->setFormatCode('"Rp "#,##0_);("Rp "#,##0)');
            return true;
        }

        // Kolom Qty (Tipe Data Integer)
        if ($cell->getColumn() === 'Y' && is_numeric($value)) {
            $cell->setValueExplicit((int) $value, DataType::TYPE_NUMERIC);
            $cell->getStyle()->getNumberFormat()->setFormatCode('#,##0');
            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function collection()
    {
        $query = Service::with(['lokasi', 'details']);
        $query = $this->applyFilters($query);

        $services = $query->orderBy('created_at', 'desc')->orderBy('id', 'desc')->get();

        $rows = [];
        $invoiceNumber = 1;
        $currentRow = 2; // Baris data dimulai di baris 2 (setelah header di baris 1)

        foreach ($services as $service) {
            $namaDealer = $this->dealers->get($service->dealer_code) ?? ($service->lokasi->nama_lokasi ?? $service->dealer_code);
            $details = $service->details;
            $detailCount = $details->count();

            $startRow = $currentRow;

            if ($detailCount > 0) {
                foreach ($details as $detail) {
                    $rows[] = [
                        $invoiceNumber,
                        $service->invoice_no ?? '-',
                        $service->reg_date ? Carbon::parse($service->reg_date)->format('Y-m-d') : '-',
                        $service->dealer_code ?? '-',
                        $namaDealer,
                        $service->yss ?? '-',
                        $service->point ?? '-',
                        $service->service_order ?? '-',
                        $service->plate_no ?? '-',
                        $service->work_order_no ?? '-',
                        $service->work_order_status ?? '-',
                        $service->customer_name ?? '-',
                        $service->customer_phone ?? '-',
                        $service->customer_ktp ?? '-',
                        $service->customer_npwp_no ?? '-',
                        $service->mc_brand ?? '-',
                        $service->mc_model_name ?? '-',
                        $service->mc_frame_no ?? '-',
                        // --- ITEM DETAILS (S-AB) ---
                        $detail->item_category ?? '-',
                        $detail->service_category_code ?? '-',
                        $detail->service_package_name ?? '-',
                        (float) ($detail->labor_cost_service ?? 0),
                        $detail->item_code ?? '-',
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
                    $service->invoice_no ?? '-',
                    $service->reg_date ? Carbon::parse($service->reg_date)->format('Y-m-d') : '-',
                    $service->dealer_code ?? '-',
                    $namaDealer,
                    $service->yss ?? '-',
                    $service->point ?? '-',
                    $service->service_order ?? '-',
                    $service->plate_no ?? '-',
                    $service->work_order_no ?? '-',
                    $service->work_order_status ?? '-',
                    $service->customer_name ?? '-',
                    $service->customer_phone ?? '-',
                    $service->customer_ktp ?? '-',
                    $service->customer_npwp_no ?? '-',
                    $service->mc_brand ?? '-',
                    $service->mc_model_name ?? '-',
                    $service->mc_frame_no ?? '-',
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

            // Jika 1 invoice memiliki lebih dari 1 item detail, lakukan merge vertikal per invoice
            if ($startRow < $endRow) {
                // Merge Header Info Invoice (A sampai R)
                for ($col = 'A'; $col !== 'S'; $col++) {
                    $this->mergeRanges[] = "{$col}{$startRow}:{$col}{$endRow}";
                }

                // Cek apakah seluruh detail item pada invoice ini memiliki Kategori Service yang sama (misal KSG / KSB)
                $firstCategory = $details->first()->service_category_code ?? null;
                $allSameCategory = $details->every(function ($d) use ($firstCategory) {
                    return ($d->service_category_code ?? null) === $firstCategory;
                });

                if ($allSameCategory && $firstCategory !== null) {
                    $this->mergeRanges[] = "T{$startRow}:T{$endRow}"; // Merge Vertikal Kolom Kategori Service
                }

                // Cek apakah seluruh detail item pada invoice ini memiliki Paket Service yang sama
                $firstPackage = $details->first()->service_package_name ?? null;
                $allSamePackage = $details->every(function ($d) use ($firstPackage) {
                    return ($d->service_package_name ?? null) === $firstPackage;
                });

                if ($allSamePackage && $firstPackage !== null) {
                    $this->mergeRanges[] = "U{$startRow}:U{$endRow}"; // Merge Vertikal Kolom Paket Service
                }

                // Merge Total Keuangan & Info Invoice (AC sampai AT)
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

    /**
     * RUMUS PATEN KONTRAK BISNIS: Perhitungan Total Tanpa KSG
     */
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

        $details = ServiceDetail::with('service')
            ->whereHas('service', function ($q) {
                $this->applyFilters($q);
                $q->where('balance', '<', 0);
            })->get();

        $ksgLaborSum = 0;

        foreach ($details as $item) {
            $pkgName = strtoupper($item->service_package_name ?? '');
            $laborCost = $item->labor_cost_service;

            if (str_contains($pkgName, 'KSG') || str_contains($pkgName, 'CLAIM')) {
                if ($laborCost > 0) {
                    $ksgLaborSum += $laborCost;
                } else {
                    $modelName = strtoupper($item->service->mc_model_name ?? '');

                    if (str_contains($pkgName, 'KSG1')) {
                        if (str_contains($modelName, 'MX KING')) {
                            $ksgLaborSum += 28000;
                        } else {
                            $ksgLaborSum += 24000;
                        }
                    } elseif (str_contains($pkgName, 'KSG2')) {
                        $ksgLaborSum += 25000;
                    } elseif (str_contains($pkgName, 'KSG3')) {
                        $ksgLaborSum += 25000;
                    } elseif (str_contains($pkgName, 'KSG4')) {
                        if (str_contains($modelName, 'NEO')) {
                            $ksgLaborSum += 42000;
                        } else {
                            $ksgLaborSum += 29000;
                        }
                    } elseif (str_contains($pkgName, 'CLAIM')) {
                        $ksgLaborSum += 16000;
                    }
                }
            }
        }

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
                $lastRow = $this->totalDataRows + 1; // +1 untuk header di baris 1
                $lastColLetter = 'AT'; // Kolom 46

                // 1. Ubah Default Font Size ke 9 untuk Seluruh Spreadsheet
                $sheet->getParent()->getDefaultStyle()->getFont()->setSize(9);

                // 2. Jalankan penggabungan sel vertikal per invoice
                foreach ($this->mergeRanges as $range) {
                    $sheet->mergeCells($range);
                }

                // 3. Aktifkan AutoFilter di Baris 1
                $sheet->setAutoFilter("A1:{$lastColLetter}1");

                // 4. Freeze Pane di Baris 2 (Header tetap terlihat saat scroll)
                $sheet->freezePane('A2');

                // 5. Tambahkan Baris TOTAL SUMMARY dan TOTAL (TANPA KSG) di bagian bawah jika ada data
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

                        if ($col !== 'Y') {
                            $sheet->getStyle("{$col}{$totalRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0_);("Rp "#,##0)');
                        } else {
                            $sheet->getStyle("{$col}{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
                        }
                    }

                    $totalStyle = [
                        'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '000000']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE2EFDA']], // Soft Green Accent
                        'borders' => [
                            'top' => ['borderStyle' => Border::BORDER_THIN],
                            'bottom' => ['borderStyle' => Border::BORDER_THIN],
                        ],
                    ];
                    $sheet->getStyle("A{$totalRow}:{$lastColLetter}{$totalRow}")->applyFromArray($totalStyle);

                    // --- BARIS 2: TOTAL (TANPA KSG) (RUMUS PATEN KODE AWAL) ---
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

                    // Qty tetap sama dengan SUM
                    $sheet->setCellValue("Y{$totalNonKsgRow}", "=SUM(Y2:Y{$lastRow})");
                    $sheet->getStyle("Y{$totalNonKsgRow}")->getNumberFormat()->setFormatCode('#,##0');

                    // Nilai Keuangan Tanpa KSG menggunakan hasil kalkulasi paten
                    foreach ($colMap as $col => $key) {
                        $val = $nonKsgTotal->$key ?? 0;
                        $sheet->setCellValue("{$col}{$totalNonKsgRow}", $val);
                        $sheet->getStyle("{$col}{$totalNonKsgRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0_);("Rp "#,##0)');
                    }

                    $nonKsgStyle = [
                        'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '006100']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFC6EFCE']], // Light Green Fill
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
            // Header Baris 1: Font Size 9 Bold
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size' => 9,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF1F4E78'], // Dark Blue Navy Professional
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => false,
                ],
            ],
            // Alignment vertikal tengah dan font size 9 untuk seluruh data
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