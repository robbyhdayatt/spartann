<?php

namespace App\Exports;

use App\Models\Service;
use App\Models\Lokasi;
use Maatwebsite\Excel\Concerns\FromQuery; // <-- Kembali ke FromQuery
use Maatwebsite\Excel\Concerns\WithMapping; // <-- Kembali ke WithMapping
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Illuminate\Database\Eloquent\Builder; // <-- Gunakan Builder lagi
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
use PhpOffice\PhpSpreadsheet\Style\NumberFormat; // <-- Tambahkan ini

// Hapus: use Illuminate\Support\Collection;
// Hapus: use Maatwebsite\Excel\Concerns\FromCollection;

class ServiceDailyReportExport extends DefaultValueBinder implements
    FromQuery, // <-- Ubah kembali
    WithMapping, // <-- Ubah kembali
    WithHeadings,
    ShouldAutoSize,
    WithEvents,
    WithStyles,
    WithCustomValueBinder
    // Hapus: FromCollection
{
    protected $dealerCode;
    protected $filterDate;
    protected $dealers;
    private $totalRows = 0; // Kembali ke nama variabel awal
    private $headerRowCount = 2;

    public function __construct($dealerCode, $filterDate)
    {
        $this->dealerCode = $dealerCode;
        $this->filterDate = $filterDate;
        $this->dealers = Lokasi::where('tipe', 'DEALER')->pluck('nama_gudang', 'kode_gudang');
    }

    // Binder: paksa beberapa kolom ke string (KTP(L), Telepon(O), No Rangka(R), Parts No.(V))
    public function bindValue(Cell $cell, $value)
    {
        // Sesuaikan Kolom: KTP(L), Telepon(O), No Rangka(R), Parts No.(V)
        if (in_array($cell->getColumn(), ['L', 'O', 'R', 'V'])) { // <-- Parts No. kembali ke V
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        // Format angka untuk kolom harga detail (Y) jika nilainya string multi-baris
        if ($cell->getColumn() === 'Y' && is_string($value) && strpos($value, "\n") !== false) {
             // Biarkan default binder menanganinya atau bisa coba atur format di sini jika perlu
             // Tapi lebih aman atur style wrap text saja
        }
         // Format angka untuk kolom kuantitas detail (X)
        if ($cell->getColumn() === 'X' && is_string($value) && strpos($value, "\n") !== false) {
             // Tidak perlu format khusus, biarkan string
        }
        // Format angka untuk kolom labor cost detail (U)
        if ($cell->getColumn() === 'U' && is_string($value) && strpos($value, "\n") !== false) {
            // Biarkan default binder menanganinya atau atur format Rp
        }

        // Format angka Rupiah untuk kolom Amount Breakdown & Totals (AB - AN, Kecuali AL)
        if (in_array($cell->getColumn(), ['AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AM', 'AN'])) {
            // Hanya format jika nilainya numerik
             if (is_numeric($value)) {
                $cell->setValueExplicit($value, DataType::TYPE_NUMERIC);
                $cell->getStyle()->getNumberFormat()->setFormatCode("Rp #,##0_);(Rp #,##0)");
                return true;
             }
        }


        return parent::bindValue($cell, $value);
    }

    // --- Tambahkan kembali method query() ---
    public function query(): Builder
    {
        $query = Service::query()
                 ->with(['lokasi', 'details']) // Tetap load 'details'
                 ->orderBy('created_at', 'desc');

        if ($this->filterDate) {
            $query->whereDate('services.created_at', $this->filterDate);
        }

        if ($this->dealerCode && $this->dealerCode !== 'all') {
            $query->where('dealer_code', $this->dealerCode);
        }

        // Hitung total baris berdasarkan Service (bukan detail)
        $this->totalRows = (clone $query)->count();
        return $query;
    }
    // --- Akhir method query() ---


    // --- HAPUS method collection() ---


    // --- Tambahkan kembali method map() ---
    /**
     * @var Service $service
     */
    public function map($service): array
    {
        $namaDealer = $this->dealers->get($service->dealer_code) ?? $service->dealer_code;

        // Gabungkan data detail menjadi string multi-baris
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
            // Format angka sebelum digabung
            $detailCols['labor_cost_service'][] = $this->formatRupiahExcel($detail->labor_cost_service);
            $detailCols['item_code'][] = $detail->item_code;
            $detailCols['item_name'][] = $detail->item_name;
            $detailCols['quantity'][] = $detail->quantity; // Kuantitas tanpa format Rp
            $detailCols['price'][] = $this->formatRupiahExcel($detail->price);
        }

        // Gunakan PHP_EOL atau "\n" sebagai pemisah baris dalam sel Excel
        $separator = PHP_EOL; //"\n";

        return [
            // Kolom A-R (Data Service Utama Bagian 1)
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

            // Kolom S-Y (Kolom Detail Baru - Digabung)
            implode($separator, $detailCols['service_category_code']),
            implode($separator, $detailCols['service_package_name']),
            implode($separator, $detailCols['labor_cost_service']),
            implode($separator, $detailCols['item_code']),
            implode($separator, $detailCols['item_name']),
            implode($separator, $detailCols['quantity']),
            implode($separator, $detailCols['price']),

            // Kolom Z-AQ (Data Service Utama Bagian 2)
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
    // --- Akhir method map() ---

     /**
     * Helper function to format number as Rupiah for Excel display within concatenated strings.
     * Note: This keeps it as a string, Excel won't treat it as a number for SUM.
     */
    private function formatRupiahExcel($number)
    {
        if (!is_numeric($number)) {
            return $number; // Return original if not numeric
        }
        // Format sederhana tanpa tanda kurung negatif, karena ini string
        return 'Rp ' . number_format($number, 0, ',', '.');
    }


    // headings() tetap sama
    public function headings(): array
    {
        return [
            // Kolom A-R (Data Service Utama Bagian 1)
            'YSS', 'Kode Dealer', 'Nama Dealer', 'Point', 'Tanggal Service', 'Service Order',
            'No Polisi', 'No Work Order', 'Status Work Order', 'No Invoice',
            'Nama Pelanggan', 'KTP Pelanggan', 'NPWP No Pelanggan', 'NPWP Nama Pelanggan', 'Telepon Pelanggan',
            'Brand Motor', 'Model Motor', 'No Rangka Motor',

            // Kolom S-Y (Kolom Detail Baru)
            'Service Category', 'Service Package', 'Labor cost Service', 'Parts No.', 'Parts Name', 'Parts Qty', 'Parts Price',

            // Kolom Z-AQ (Data Service Utama Bagian 2)
            'Tipe Pembayaran', 'Kode Transaksi', 'Jumlah E-Payment', 'Jumlah Cash', 'Jumlah Debit',
            'Total DP', 'Total Labor', 'Total Part Service', 'Total Oil Service',
            'Total Retail Parts', 'Total Retail Oil',
            'Total Amount (Gross)', 'Benefit Amount', 'Total Payment (Net)', 'Balance',
            'Nama Teknisi', 'Waktu Cetak', 'Tanggal Import',
        ];
    }


    // registerEvents() perlu disesuaikan kembali
public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;

                // Sisipkan 1 baris di atas untuk header grup
                $sheet->insertNewRowBefore(1, 1);
                $groupHeaderRow = 1;
                $headerRow = 2; // Judul kolom (dari WithHeadings) ada di baris 2

                $headings = $this->headings();
                $numHeadings = count($headings);
                $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($numHeadings);

                // Definisikan Header Grup (Pastikan range kolom sudah benar)
                $groupHeaders = [
                    // Kolom => ['range' => 'StartCol<Row1>:EndCol<Row1>', 'title' => 'Judul Grup']
                    'K' => ['range' => "K{$groupHeaderRow}:O{$groupHeaderRow}", 'title' => 'Customer Information'], // K sampai O
                    'P' => ['range' => "P{$groupHeaderRow}:R{$groupHeaderRow}", 'title' => 'M/C Information'],    // P sampai R
                    'S' => ['range' => "S{$groupHeaderRow}:Y{$groupHeaderRow}", 'title' => 'Service Details'],   // S sampai Y
                    'AE' => ['range' => "AE{$groupHeaderRow}:AJ{$groupHeaderRow}", 'title' => 'Amount Breakdown'], // AE sampai AJ
                    'AK' => ['range' => "AK{$groupHeaderRow}:AN{$groupHeaderRow}", 'title' => 'Totals & Balance'] // AK sampai AN
                ];

                // --- Langkah 1: Terapkan Header Grup ---
                $groupedColumns = []; // Catat kolom mana saja yang sudah masuk grup
                foreach ($groupHeaders as $startCol => $group) {
                    $sheet->mergeCells($group['range']);
                    $sheet->setCellValue("{$startCol}{$groupHeaderRow}", $group['title']);
                    // Catat semua indeks kolom dalam range grup ini
                    list($rangeStart, $rangeEnd) = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::rangeBoundaries($group['range']);
                    for ($colIdx = $rangeStart[0]; $colIdx <= $rangeEnd[0]; $colIdx++) {
                         $groupedColumns[$colIdx] = true; // Gunakan indeks kolom sebagai key
                    }
                }

                // --- Langkah 2: Merge Header Kolom Tunggal secara Vertikal ---
                for ($colIndex = 1; $colIndex <= $numHeadings; $colIndex++) {
                    // Jika indeks kolom ini TIDAK ADA dalam $groupedColumns
                    if (!isset($groupedColumns[$colIndex])) {
                        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                         // Ambil nilai header dari baris 2 (yang dibuat oleh WithHeadings)
                        $headerValue = $sheet->getCell("{$colLetter}{$headerRow}")->getValue();
                        // Set nilai di baris 1
                        $sheet->setCellValue("{$colLetter}{$groupHeaderRow}", $headerValue);
                        // Merge sel A1:A2, B1:B2, dst.
                        $sheet->mergeCells("{$colLetter}{$groupHeaderRow}:{$colLetter}{$headerRow}");
                    }
                }

                // --- Style Header ---
                $headerStyle = [
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4F81BD']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                ];
                // Terapkan style ke kedua baris header
                $sheet->getStyle("A{$groupHeaderRow}:{$lastColLetter}{$headerRow}")->applyFromArray($headerStyle);
                // Atur tinggi baris header agar teks wrap terlihat (sesuaikan jika perlu)
                $sheet->getRowDimension($groupHeaderRow)->setRowHeight(25);
                $sheet->getRowDimension($headerRow)->setRowHeight(40);


                // --- Hitung Total --- (Tidak ada perubahan di sini)
                if ($this->totalRows > 0) {
                    $firstDataRow = $this->headerRowCount + 1;
                    $lastDataRow = $this->totalRows + $this->headerRowCount;
                    $totalRow = $lastDataRow + 1;

                    $mergeUntilCol = 'AA'; // Kolom sebelum kolom pertama yg dijumlah (AB)
                    $sheet->setCellValue("A{$totalRow}", 'TOTAL');
                    $sheet->mergeCells("A{$totalRow}:{$mergeUntilCol}{$totalRow}");

                    $columnsToSum = [
                        'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AM', 'AN'
                    ];

                    foreach ($columnsToSum as $column) {
                        $sheet->setCellValue("{$column}{$totalRow}", "=SUM({$column}{$firstDataRow}:{$column}{$lastDataRow})");
                        $sheet->getStyle("{$column}{$totalRow}")
                              ->getNumberFormat()
                              ->setFormatCode("Rp #,##0_);(Rp #,##0)");
                    }

                    $totalRowStyle = [
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFE0B2']],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                    ];
                    $sheet->getStyle("A{$totalRow}:{$lastColLetter}{$totalRow}")->applyFromArray($totalRowStyle);
                    $sheet->getStyle("A{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                }

                // Freeze Pane (Tetap sama)
                $sheet->freezePane('A' . ($this->headerRowCount + 1));
            },
        ];
    }


    // styles() perlu menambahkan wrap text untuk kolom detail
    public function styles(Worksheet $sheet)
    {
        // Terapkan alignment vertikal top ke semua baris data
        $lastDataRow = $this->totalRows + $this->headerRowCount; // Gunakan totalRows dari query Service
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($this->headings())); // AQ

        $stylesArray = [
            "A" . ($this->headerRowCount + 1) . ":{$lastColLetter}{$lastDataRow}" => [
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP],
            ],
            // --- Tambahkan Wrap Text untuk kolom detail S sampai Y ---
            "S" . ($this->headerRowCount + 1) . ":Y{$lastDataRow}" => [
                 'alignment' => [
                    'wrapText' => true,
                    'vertical' => Alignment::VERTICAL_TOP // Pastikan vertical top juga diterapkan
                 ],
            ],
            // --- Akhir penambahan Wrap Text ---
        ];

        // Format angka untuk kolom Amount di baris data (sudah ditangani di bindValue, tapi bisa juga di sini)
        /*
        $amountCols = ['AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN'];
        foreach($amountCols as $col) {
            $stylesArray["{$col}" . ($this->headerRowCount + 1) . ":{$col}{$lastDataRow}"] = [
                'numberFormat' => ['formatCode' => "Rp #,##0_);(Rp #,##0)"]
            ];
        }
        */

        return $stylesArray;
    }
}