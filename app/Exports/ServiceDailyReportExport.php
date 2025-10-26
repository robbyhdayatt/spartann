<?php

namespace App\Exports;

use App\Models\Service;
// Hapus ServiceDetail, kita tidak query dari situ lagi
use App\Models\Lokasi;
use Maatwebsite\Excel\Concerns\FromQuery;
// HAPUS WithHeadings, kita akan buat manual
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Untuk perbaikan format Teks (KTP, Kode Item)
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

// ++ PERHATIKAN: extends DefaultValueBinder ++
class ServiceDailyReportExport extends DefaultValueBinder implements
    FromQuery,
    // WithHeadings, // <-- HAPUS INTERFACE INI
    WithMapping,
    ShouldAutoSize,
    WithStrictNullComparison,
    WithEvents,
    WithStyles,
    WithColumnFormatting,
    WithCustomValueBinder // Implementasikan ini
{
    protected $dealerCode;
    protected $filterDate;
    protected $dealers;
    private $totalRows = 0;
    // Hapus $invoiceTotals, kita akan SUM langsung di Excel

    public function __construct($dealerCode, $filterDate)
    {
        $this->dealerCode = $dealerCode;
        $this->filterDate = $filterDate;
        $this->dealers = Lokasi::where('tipe', 'DEALER')->pluck('nama_gudang', 'kode_gudang');
        // Hapus $this->calculateInvoiceTotals();
    }

    // ++ FUNGSI KRITIS: UNTUK FIX KTP & NO RANGKA ++
    public function bindValue(Cell $cell, $value)
    {
        // Kolom L (KTP), O (Telepon), R (No Rangka)
        if (in_array($cell->getColumn(), ['L', 'O', 'R'])) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        return parent::bindValue($cell, $value);
    }

    /**
    * @return Builder
    */
    public function query(): Builder
    {
        // ++ PERUBAHAN: Query utama sekarang ke tabel 'services' ++
        $query = Service::query()
                 ->with(['lokasi']) // Eager load relasi lokasi
                 ->orderBy('created_at', 'desc');

        // Terapkan filter tanggal (pasti ada)
        if ($this->filterDate) {
            $query->whereDate('created_at', $this->filterDate);
        }
        
        // Terapkan filter dealer jika ada (bukan 'all')
        if ($this->dealerCode && $this->dealerCode !== 'all') {
            $query->where('dealer_code', $this->dealerCode);
        }

        $this->totalRows = (clone $query)->count();
        return $query; // Mengembalikan Eloquent\Builder dari Service
    }

/**
    * @param Service $service
    */
    public function map($service): array
    {
        $namaDealer = $this->dealers->get($service->dealer_code) ?? $service->dealer_code;

        return [
            // A-J (Info Dasar)
            $service->yss, $service->dealer_code, $namaDealer, $service->point,
            $service->reg_date ? Carbon::parse($service->reg_date)->format('Y-m-d') : null,
            $service->service_order, $service->plate_no, $service->work_order_no,
            $service->work_order_status, $service->invoice_no,
            
            // K-O (Customer Info)
            $service->customer_name, $service->customer_ktp, $service->customer_npwp_no,
            $service->customer_npwp_name, $service->customer_phone,
            
            // P-R (M/C Info)
            $service->mc_brand, $service->mc_model_name, $service->mc_frame_no,
            
            // S-W (Payment Info)
            $service->payment_type, $service->transaction_code,

            // ++ PERBAIKAN: KEMBALIKAN (float) CAST DI SINI ++
            // Ini akan mengubah string "75000.00" menjadi angka 75000
            
            (float) $service->e_payment_amount, // Kolom U
            (float) $service->cash_amount,      // Kolom V
            (float) $service->debit_amount,     // Kolom W
            
            // X-AC (Amount Group - YANG ANDA MINTA)
            (float) $service->total_down_payment,    // Kolom X
            (float) $service->total_labor,           // Kolom Y
            (float) $service->total_part_service,  // Kolom Z
            (float) $service->total_oil_service,   // Kolom AA
            (float) $service->total_retail_parts,  // Kolom AB
            (float) $service->total_retail_oil,    // Kolom AC
            
            // AD-AG (Other Totals - YANG ANDA MINTA)
            (float) $service->total_amount,        // Kolom AD
            (float) $service->benefit_amount,    // Kolom AE
            (float) $service->total_payment,       // Kolom AF
            (float) $service->balance,             // Kolom AG
            // ++ END PERBAIKAN ++

            // AH-AJ (Other Info)
            $service->technician_name,
            $service->printed_at ? Carbon::parse($service->printed_at)->format('Y-m-d H:i:s') : null,
            $service->created_at ? Carbon::parse($service->created_at)->format('Y-m-d H:i:s') : null,
        ];
    }
    /**
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                
                // 1. Sisipkan 2 baris baru di atas untuk header kustom
                // Data dari map() akan mulai di baris 3
                $sheet->insertNewRowBefore(1, 2);
                $groupHeaderRow = 1;
                $headerRow = 2; // Header sub-kolom ada di baris 2
                
                // 2. Tulis header sub-kolom (dari headings()) secara manual di baris 2
                $headings = [
                    'A' => 'YSS', 'B' => 'Kode Dealer', 'C' => 'Nama Dealer', 'D' => 'Point', 'E' => 'Tanggal Service', 'F' => 'Service Order',
                    'G' => 'No Polisi', 'H' => 'No Work Order', 'I' => 'Status Work Order', 'J' => 'No Invoice', 
                    'K' => 'Nama Pelanggan', 'L' => 'KTP Pelanggan', 'M' => 'NPWP No Pelanggan', 'N' => 'NPWP Nama Pelanggan', 'O' => 'Telepon Pelanggan',
                    'P' => 'Brand Motor', 'Q' => 'Model Motor', 'R' => 'No Rangka Motor',
                    // Kolom Detail Dihapus
                    'S' => 'Tipe Pembayaran', 'T' => 'Kode Transaksi', 'U' => 'Jumlah E-Payment', 'V' => 'Jumlah Cash', 'W' => 'Jumlah Debit',
                    'X' => 'Total DP', 'Y' => 'Total Labor', 'Z' => 'Total Part Service', 'AA' => 'Total Oil Service',
                    'AB' => 'Total Retail Parts', 'AC' => 'Total Retail Oil', 
                    'AD' => 'Total Amount (Gross)', 'AE' => 'Benefit Amount', 'AF' => 'Total Payment (Net)', 'AG' => 'Balance',
                    'AH' => 'Nama Teknisi', 'AI' => 'Waktu Cetak', 'AJ' => 'Tanggal Import',
                ];
                foreach ($headings as $column => $title) {
                    $sheet->setCellValue("{$column}{$headerRow}", $title);
                }

                // 3. Tulis header grup di Baris 1
                $sheet->mergeCells("K{$groupHeaderRow}:O{$groupHeaderRow}"); 
                $sheet->setCellValue("K{$groupHeaderRow}", 'Customer Information');
                
                $sheet->mergeCells("P{$groupHeaderRow}:R{$groupHeaderRow}"); 
                $sheet->setCellValue("P{$groupHeaderRow}", 'M/C Information');
                
                // ++ PERUBAHAN KOORDINAT: Kolom Amount sekarang di X-AC ++
                $sheet->mergeCells("X{$groupHeaderRow}:AC{$groupHeaderRow}"); 
                $sheet->setCellValue("X{$groupHeaderRow}", 'Amount');
                
                // 4. Gabungkan sel header yang tidak punya grup (di Baris 1 & 2)
                $singleHeaders = [
                    'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', // Info dasar
                    'S', 'T', 'U', 'V', 'W', // Info bayar
                    'AD', 'AE', 'AF', 'AG', // Info total lain
                    'AH', 'AI', 'AJ' // Info teknisi/waktu
                ];
                
                foreach ($singleHeaders as $column) {
                    // Ambil nilai dari baris 2
                    $value = $sheet->getCell("{$column}{$headerRow}")->getValue();
                    // Set nilai itu ke baris 1
                    $sheet->setCellValue("{$column}{$groupHeaderRow}", $value);
                    // Baru merge
                    $sheet->mergeCells("{$column}{$groupHeaderRow}:{$column}{$headerRow}");
                }

                // 5. Atur Style Header (Baris 1 & 2)
                $headerStyle = [
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4F81BD']], // Biru
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFFFFFFF']]]
                ];
                // ++ PERUBAHAN KOORDINAT: Range header sekarang A1:AJ2 ++
                $sheet->getStyle("A{$groupHeaderRow}:AJ{$headerRow}")->applyFromArray($headerStyle);
                
                // 6. Logika Baris TOTAL
                if ($this->totalRows > 0) {
                    // Data sekarang mulai dari baris 3
                    $firstDataRow = 3;
                    $lastDataRow = $this->totalRows + $headerRow; 
                    $totalRow = $lastDataRow + 1;

                    $sheet->setCellValue("A{$totalRow}", 'GRAND TOTAL');
                    // ++ PERUBAHAN KOORDINAT: Gabung sampai T (sebelum kolom hitungan) ++
                    $sheet->mergeCells("A{$totalRow}:T{$totalRow}"); 
                    
                    // ++ PERUBAHAN: Gunakan SUM() Excel biasa, mulai dari kolom 'U' ++
                    $columnsToSum = range('U', 'AG'); // U (E-Payment) sampai AG (Balance)
                    foreach ($columnsToSum as $column) {
                        $sheet->setCellValue("{$column}{$totalRow}", "=SUM({$column}{$firstDataRow}:{$column}{$lastDataRow})");
                    }
                    // Kolom AH, AI, AJ (Teks) dikosongkan

                    // Style Baris Total
                    $totalRowStyle = [
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFE0B2']], // Oranye muda
                        'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN]],
                    ];
                    // ++ PERUBAHAN KOORDINAT: Style A sampai AJ ++
                    $sheet->getStyle("A{$totalRow}:AJ{$totalRow}")->applyFromArray($totalRowStyle);
                    
                    // Format Angka Baris Total
                    $numberFormat = '#.##0';
                    $sheet->getStyle("U{$totalRow}:AG{$totalRow}")->getNumberFormat()->setFormatCode($numberFormat);
                    $sheet->getStyle("A{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
                
                // 7. Bekukan Header (Baris 1 & 2)
                $sheet->freezePane('A3'); // Bekukan di sel A3 (di bawah header)
            },
        ];
    }

    /**
     * @param Worksheet $sheet
     */
    public function styles(Worksheet $sheet)
    {
        // Atur perataan untuk data (mulai dari baris 3)
        return [
            // Data dimulai dari baris 3
            'A3:AJ' . ($this->totalRows + 2) => [
                 'alignment' => ['vertical' => Alignment::VERTICAL_TOP],
            ],
            // Rata kiri (Teks) - Sampai Kolom T
            'A:T' => [ 'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT] ],
            // Rata kanan (Angka) - Mulai Kolom U
            'U:AG' => [ 'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT] ],
             // Rata kiri (Teks lagi)
            'AH:AJ' => [ 'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT] ],
        ];
    }

    /**
     * @return array
     */
    public function columnFormats(): array
    {
        return [
            'U:AG' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
        ];
    }
}