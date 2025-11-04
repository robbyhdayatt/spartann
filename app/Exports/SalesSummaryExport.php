<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
// IMPORT UNTUK STYLING
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// HAPUS 'WithColumnDataTypes' DARI DAFTAR IMPLEMENTS
class SalesSummaryExport implements
    FromQuery,
    WithHeadings,
    WithMapping,
    WithTitle,
    WithStyles,
    WithColumnFormatting,
    WithEvents
{
    protected $startDate;
    protected $endDate;
    protected $grandTotals; // Kita simpan grand total di sini

    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function query()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $query = DB::table('penjualan_details')
            ->join('penjualans', 'penjualan_details.penjualan_id', '=', 'penjualans.id')
            ->join('barangs', 'penjualan_details.barang_id', '=', 'barangs.id')
            ->select(
                'barangs.part_code',
                'barangs.part_name',
                DB::raw('SUM(penjualan_details.qty_jual) as total_qty'),
                DB::raw('SUM(penjualan_details.subtotal) as total_penjualan'),
                DB::raw('SUM(penjualan_details.qty_jual * barangs.harga_modal) as total_modal'),
                DB::raw('SUM(penjualan_details.subtotal) - SUM(penjualan_details.qty_jual * barangs.harga_modal) as total_keuntungan')
            )
            ->whereBetween('penjualans.tanggal_jual', [$this->startDate, $this->endDate])
            ->groupBy('penjualan_details.barang_id', 'barangs.part_code', 'barangs.part_name')
            ->orderBy('total_qty', 'desc');

        // Filter lokasi untuk user non-admin
        if (!$user->hasRole(['SA', 'PIC', 'MA']) && $user->lokasi_id) {
            $query->where('penjualans.lokasi_id', $user->lokasi_id);
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
        // Ini akan menjadi header di Row 4
        return [
            'Kode Barang',
            'Nama Barang',
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
            "\t" . $row->part_code,

            $row->part_name,
            $row->total_qty,
            $row->total_penjualan,
            $row->total_modal,
            $row->total_keuntungan,
        ];
    }

    public function title(): string
    {
        return 'Laporan Penjualan';
    }

    // HAPUS FUNGSI 'columnTypes()' KARENA TIDAK DITEMUKAN

    /**
     * Menerapkan format angka untuk kolom tertentu.
     */
    public function columnFormats(): array
    {
        return [
            // Format kolom D, E, F sebagai mata uang Rupiah
            'D' => '"Rp " #,##0_,-',
            'E' => '"Rp " #,##0_,-',
            'F' => '"Rp " #,##0_,-',
        ];
    }

    /**
     * Menerapkan style (Bold, Background) pada baris Header.
     */
    public function styles(Worksheet $sheet)
    {
        // Baris 4 adalah baris header kita (setelah 3 baris judul disisipkan)
        $headerRow = 4;

        // Style baris Header (Row 4)
        $sheet->getStyle("A{$headerRow}:F{$headerRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFD9D9D9'], // Warna abu-abu muda
            ],
        ]);
    }

    /**
     * Menjalankan event setelah sheet dibuat.
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // 1. Sisipkan 3 baris baru di paling atas
                $sheet->insertNewRowBefore(1, 3);

                // 2. Tambahkan Judul Laporan di baris 1
                $sheet->setCellValue('A1', 'Laporan Ringkasan Penjualan');
                $sheet->mergeCells('A1:F1');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
                ]);

                // 3. Tambahkan Periode Tanggal di baris 2
                $sheet->setCellValue('A2', 'Periode: ' . \Carbon\Carbon::parse($this->startDate)->format('d-m-Y') . ' s/d ' . \Carbon\Carbon::parse($this->endDate)->format('d-m-Y'));
                $sheet->mergeCells('A2:F2');
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // 4. Atur lebar kolom agar otomatis (Auto-size)
                foreach (range('A', 'F') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                // 5. TAMBAHKAN FOOTER SECARA MANUAL
                $lastRow = $sheet->getHighestRow();
                $footerRow = $lastRow + 1; // Baris baru untuk footer

                // Tulis data Grand Total
                $sheet->setCellValue("B{$footerRow}", 'GRAND TOTAL');
                $sheet->setCellValue("C{$footerRow}", $this->grandTotals['qty']);
                $sheet->setCellValue("D{$footerRow}", $this->grandTotals['penjualan']);
                $sheet->setCellValue("E{$footerRow}", $this->grandTotals['modal']);
                $sheet->setCellValue("F{$footerRow}", $this->grandTotals['keuntungan']);

                // Terapkan style ke baris footer
                $sheet->getStyle("A{$footerRow}:F{$footerRow}")->applyFromArray([
                    'font' => ['bold' => true],
                ]);

                // Format angka di footer
                $sheet->getStyle("D{$footerRow}:F{$footerRow}")
                      ->getNumberFormat()
                      ->setFormatCode('"Rp " #,##0_,-');

                // Pusatkan Grand Total di kolom B
                $sheet->getStyle("B{$footerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
