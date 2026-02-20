<?php

namespace App\Exports;

use App\Models\PenjualanDetail;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
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
use Illuminate\Support\Facades\Auth;

class SalesSummaryExport extends DefaultValueBinder implements 
    FromCollection, WithHeadings, WithMapping, ShouldAutoSize, 
    WithStyles, WithEvents, WithCustomValueBinder
{
    protected $startDate;
    protected $endDate;
    private $rowCount = 0;
    private $totalQty = 0;
    private $totalPenjualan = 0;

    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function bindValue(Cell $cell, $value)
    {
        if ($cell->getColumn() === 'F') { // Asumsi Kolom F adalah Kode Barang
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        return parent::bindValue($cell, $value);
    }

    public function collection()
    {
        $user = Auth::user();
        $query = PenjualanDetail::with(['penjualan.konsumen', 'penjualan.sales', 'barang', 'penjualan.lokasi'])
            ->whereHas('penjualan', function ($q) use ($user) {
                $q->whereBetween('tanggal_jual', [$this->startDate, $this->endDate]);
                if ($user->isPusat()) {
                    $q->whereHas('lokasi', fn($l) => $l->where('tipe', 'DEALER'));
                } elseif ($user->isDealer()) {
                    $q->where('lokasi_id', $user->lokasi_id);
                }
            })->latest();

        $data = $query->get();
        $this->rowCount = $data->count();
        return $data;
    }

    public function headings(): array
    {
        return [
            'Tanggal Transaksi',
            'No Faktur',
            'Lokasi/Dealer',
            'Nama Konsumen',
            'Nama Sales',
            'Kode Barang',
            'Nama Barang',
            'Qty Terjual',
            'Harga Satuan',
            'Total Subtotal',
        ];
    }

    public function map($detail): array
    {
        $this->totalQty += $detail->qty_jual;
        $this->totalPenjualan += $detail->subtotal;

        return [
            $detail->penjualan->tanggal_jual->format('d-m-Y'),
            $detail->penjualan->nomor_faktur,
            $detail->penjualan->lokasi->nama_lokasi ?? '-',
            $detail->penjualan->konsumen->nama_konsumen ?? '-',
            $detail->penjualan->sales->nama ?? '-',
            $detail->barang->part_code ?? '-',
            $detail->barang->part_name ?? '-',
            $detail->qty_jual,
            $detail->harga_jual,
            $detail->subtotal,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => '059669']], // Hijau
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
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

                $sheet->getStyle('I2:J' . $totalRow)->getNumberFormat()->setFormatCode('_("Rp"* #,##0_);_("Rp"* (#,##0);_("Rp"* "-"_);_(@_)');

                // Grand Total
                $sheet->setCellValue('A' . $totalRow, 'GRAND TOTAL PENJUALAN');
                $sheet->mergeCells('A' . $totalRow . ':G' . $totalRow);
                $sheet->setCellValue('H' . $totalRow, $this->totalQty);
                $sheet->setCellValue('J' . $totalRow, $this->totalPenjualan);

                $sheet->getStyle('A' . $totalRow . ':J' . $totalRow)->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'E5E7EB']],
                ]);
                $sheet->getStyle('A' . $totalRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('A1:J' . $totalRow)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => '000000']]],
                ]);
            },
        ];
    }
}