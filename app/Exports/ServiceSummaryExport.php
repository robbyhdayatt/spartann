<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Hapus 'WithColumnDataTypes' dan 'DataType'

class ServiceSummaryExport implements
    FromQuery,
    WithHeadings,
    WithMapping,
    WithTitle,
    WithStyles,
    WithColumnFormatting,
    WithEvents
    // Hapus 'WithColumnDataTypes' dari sini
{
    protected $startDate;
    protected $endDate;
    protected $invoiceNo;
    protected $grandTotals;

    public function __construct($startDate, $endDate, $invoiceNo)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->invoiceNo = $invoiceNo;
    }

    public function query()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $query = DB::table('service_details')
            ->join('services', 'service_details.service_id', '=', 'services.id')
            ->leftJoin('parts', 'service_details.item_code', '=', 'parts.kode_part')
            ->select(
                'service_details.item_code',
                'service_details.item_name',
                'service_details.item_category',
                DB::raw('SUM(service_details.quantity) as total_qty'),
                DB::raw('SUM((service_details.price * service_details.quantity) + service_details.labor_cost_service) as total_penjualan'),
                DB::raw("SUM(CASE
                                WHEN service_details.item_category != 'JASA' THEN service_details.quantity * parts.harga_satuan
                                ELSE 0
                            END) as total_modal"),
                DB::raw("SUM((service_details.price * service_details.quantity) + service_details.labor_cost_service) -
                         SUM(CASE
                                WHEN service_details.item_category != 'JASA' THEN service_details.quantity * parts.harga_satuan
                                ELSE 0
                            END) as total_keuntungan")
            )
            ->whereBetween('services.reg_date', [$this->startDate, $this->endDate])
            ->groupBy('service_details.item_code', 'service_details.item_name', 'service_details.item_category')
            ->orderBy('total_qty', 'desc');

        // Filter lokasi untuk user non-admin
        if (!$user->hasRole(['SA', 'PIC', 'MA']) && $user->lokasi_id) {
            $query->where('services.lokasi_id', $user->lokasi_id);
        }

        // Filter berdasarkan Nomor Invoice
        if ($this->invoiceNo) {
            $query->where('services.invoice_no', 'like', '%' . $this->invoiceNo . '%');
        }

        // Simpan grand total untuk footer
        $data = $query->get();
        $this->grandTotals = [
            'qty' => $data->sum('total_qty'),
            'penjualan' => $data->sum('total_penjualan'),
            'modal' => $data->sum('total_modal'),
            'keuntungan' => $data->sum('total_keuntungan'),
        ];

        // Kembalikan query untuk diproses oleh package Excel
        return $query;
    }

    public function headings(): array
    {
        return [
            // Baris 4
            'Kode Item',
            'Nama Item',
            'Kategori',
            'Qty Terjual',
            'Total Penjualan',
            'Total Modal (HPP)',
            'Total Keuntungan',
        ];
    }

    public function map($row): array
    {
        return [
            // ++ INI PERBAIKANNYA: Tambahkan karakter Tab ("\t") di depan kode ++
            "\t" . $row->item_code,
            $row->item_name,
            $row->item_category,
            $row->total_qty,
            $row->total_penjualan,
            $row->total_modal,
            $row->total_keuntungan,
        ];
    }

    // HAPUS FUNGSI 'columnTypes()' KARENA TIDAK DITEMUKAN

    public function title(): string
    {
        return 'Laporan Service';
    }

    public function columnFormats(): array
    {
        return [
            // 'A' => NumberFormat::FORMAT_TEXT, // Hapus ini, diganti trik Tab
            // Format kolom E, F, G sebagai mata uang Rupiah
            'E' => '"Rp " #,##0_,-',
            'F' => '"Rp " #,##0_,-',
            'G' => '"Rp " #,##0_,-',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Baris 4 adalah baris header kita (setelah 3 baris judul disisipkan)
        $headerRow = 4;

        // Style baris Header (Row 4)
        $sheet->getStyle("A{$headerRow}:G{$headerRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFD9D9D9'], // Warna abu-abu muda
            ],
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // 1. Sisipkan 3 baris baru di paling atas
                $sheet->insertNewRowBefore(1, 3);

                // 2. Tambahkan Judul Laporan di baris 1
                $sheet->setCellValue('A1', 'Laporan Ringkasan Service');
                $sheet->mergeCells('A1:G1');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
                ]);

                // 3. Tambahkan Periode Tanggal di baris 2
                $filterInfo = 'Periode: ' . \Carbon\Carbon::parse($this->startDate)->format('d-m-Y') . ' s/d ' . \Carbon\Carbon::parse($this->endDate)->format('d-m-Y');
                if ($this->invoiceNo) {
                    $filterInfo .= ' (Invoice: ' . $this->invoiceNo . ')';
                }
                $sheet->setCellValue('A2', $filterInfo);
                $sheet->mergeCells('A2:G2');
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // 4. Atur lebar kolom agar otomatis (Auto-size)
                foreach (range('A', 'G') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                // 5. TAMBAHKAN FOOTER SECARA MANUAL
                $lastRow = $sheet->getHighestRow();
                $footerRow = $lastRow + 1; // Baris baru untuk footer

                // Tulis data Grand Total
                $sheet->setCellValue("C{$footerRow}", 'GRAND TOTAL');
                $sheet->setCellValue("D{$footerRow}", $this->grandTotals['qty']);
                $sheet->setCellValue("E{$footerRow}", $this->grandTotals['penjualan']);
                $sheet->setCellValue("F{$footerRow}", $this->grandTotals['modal']);
                $sheet->setCellValue("G{$footerRow}", $this->grandTotals['keuntungan']);

                // Terapkan style ke baris footer
                $sheet->getStyle("A{$footerRow}:G{$footerRow}")->applyFromArray([
                    'font' => ['bold' => true],
                ]);

                // Format angka di footer
                $sheet->getStyle("E{$footerRow}:G{$footerRow}")
                      ->getNumberFormat()
                      ->setFormatCode('"Rp " #,##0_,-');

                // Pusatkan Grand Total di kolom C
                $sheet->getStyle("C{$footerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
