<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ServiceSummaryExport extends DefaultValueBinder implements 
    FromCollection, WithHeadings, ShouldAutoSize, WithStyles, 
    WithEvents, WithCustomValueBinder
{
    protected $startDate;
    protected $endDate;
    protected $invoiceNo;
    protected $lokasiId;

    protected $grandQty = 0;
    protected $grandJual = 0;
    protected $grandModal = 0;
    protected $grandProfit = 0;
    protected $rowCount = 0;

    public function __construct($startDate, $endDate, $invoiceNo, $lokasiId = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->invoiceNo = $invoiceNo;
        $this->lokasiId = $lokasiId;
    }

    public function bindValue(Cell $cell, $value)
    {
        // Paksa Kolom A (Kode Part) menjadi String
        if ($cell->getColumn() === 'A') {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        return parent::bindValue($cell, $value);
    }

    public function collection()
    {
        $validPartCodes = DB::table('converts_main')->distinct()->pluck('part_code')->toArray();

        $query = DB::table('stock_movements')
            ->join('barangs', 'stock_movements.barang_id', '=', 'barangs.id')
            ->join('services', 'stock_movements.referensi_id', '=', 'services.id')
            ->where('stock_movements.referensi_type', 'like', '%Service%')
            ->whereIn('barangs.part_code', $validPartCodes)
            ->select(
                'barangs.part_code as item_code',
                'barangs.part_name as item_name',
                DB::raw("'Sparepart' as item_category"),
                DB::raw('ABS(SUM(stock_movements.jumlah)) as total_qty'),
                // Ambil harga satuan menggunakan MAX (Karena harga konstan per barang)
                DB::raw('MAX(COALESCE(barangs.retail, 0)) as harga_jual_satuan'),
                DB::raw('MAX(COALESCE(barangs.selling_out, 0)) as hpp_satuan'),
                // Hitung total dengan mengalikan SUM qty dengan Harga Satuan
                DB::raw('ABS(SUM(stock_movements.jumlah)) * MAX(COALESCE(barangs.retail, 0)) as total_penjualan'),
                DB::raw('ABS(SUM(stock_movements.jumlah)) * MAX(COALESCE(barangs.selling_out, 0)) as total_modal'),
                DB::raw('(ABS(SUM(stock_movements.jumlah)) * MAX(COALESCE(barangs.retail, 0))) - 
                         (ABS(SUM(stock_movements.jumlah)) * MAX(COALESCE(barangs.selling_out, 0))) as total_keuntungan')
            )
            ->whereBetween('services.reg_date', [$this->startDate, $this->endDate])
            ->groupBy('barangs.id', 'barangs.part_code', 'barangs.part_name');

        if ($this->lokasiId) {
            $query->where('services.lokasi_id', $this->lokasiId);
        }

        if ($this->invoiceNo) {
            $query->where('services.invoice_no', 'like', '%' . $this->invoiceNo . '%');
        }

        $query->having('total_qty', '>', 0);
        $query->orderBy('total_qty', 'desc');

        $data = $query->get();

        $this->grandQty = $data->sum('total_qty');
        $this->grandJual = $data->sum('total_penjualan');
        $this->grandModal = $data->sum('total_modal');
        $this->grandProfit = $data->sum('total_keuntungan');
        $this->rowCount = $data->count();

        // Karena kita menggunakan stdClass dari DB::table, format array agar sesuai urutan header
        return $data->map(function ($item) {
            return [
                $item->item_code,
                $item->item_name,
                $item->item_category,
                $item->total_qty,
                $item->harga_jual_satuan,
                $item->total_penjualan,
                $item->hpp_satuan,
                $item->total_modal,
                $item->total_keuntungan,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Kode Part',
            'Nama Barang',
            'Kategori',
            'Total Qty',
            'Harga Jual Satuan (Retail)',
            'Total Penjualan',
            'HPP Satuan (Selling Out)',
            'Total HPP',
            'Total Keuntungan',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'DC2626'], // Merah gelap untuk Service
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $this->rowCount + 1;
                $totalRow = $lastRow + 1;

                // Format Rupiah untuk Kolom Harga dan Total (E, F, G, H, I)
                $sheet->getStyle('E2:I' . $totalRow)->getNumberFormat()->setFormatCode('_("Rp"* #,##0_);_("Rp"* (#,##0);_("Rp"* "-"_);_(@_)');
                $sheet->getStyle('D2:D' . $totalRow)->getNumberFormat()->setFormatCode('#,##0');

                // Baris Grand Total
                $sheet->setCellValue('A' . $totalRow, 'GRAND TOTAL');
                $sheet->mergeCells('A' . $totalRow . ':C' . $totalRow);

                $sheet->setCellValue('D' . $totalRow, $this->grandQty);
                $sheet->setCellValue('F' . $totalRow, $this->grandJual);
                $sheet->setCellValue('H' . $totalRow, $this->grandModal);
                $sheet->setCellValue('I' . $totalRow, $this->grandProfit);

                // Styling Total
                $sheet->getStyle('A' . $totalRow . ':I' . $totalRow)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'F3F4F6']],
                ]);
                $sheet->getStyle('A' . $totalRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Border Seluruh
                $sheet->getStyle('A1:I' . $totalRow)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => '000000']]],
                ]);
            },
        ];
    }
}