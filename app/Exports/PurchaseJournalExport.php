<?php

namespace App\Exports;

use App\Models\ReceivingDetail;
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

class PurchaseJournalExport extends DefaultValueBinder implements 
    FromCollection, WithHeadings, WithMapping, ShouldAutoSize, 
    WithStyles, WithEvents, WithCustomValueBinder
{
    protected $startDate;
    protected $endDate;
    private $rowCount = 0;

    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function bindValue(Cell $cell, $value)
    {
        if ($cell->getColumn() === 'E') { // Kolom Kode Barang
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        return parent::bindValue($cell, $value);
    }

    public function collection()
    {
        $data = ReceivingDetail::with(['receiving.purchaseOrder.supplier', 'barang'])
            ->whereHas('receiving', function ($query) {
                $query->whereBetween('tanggal_terima', [$this->startDate, $this->endDate]);
            })
            ->latest()->get();
        $this->rowCount = $data->count();
        return $data;
    }

    public function headings(): array
    {
        return [
            'Tanggal Terima',
            'No Penerimaan',
            'No PO',
            'Nama Supplier / Sumber',
            'Kode Barang',
            'Nama Barang',
            'Qty Diterima',
        ];
    }

    public function map($detail): array
    {
        return [
            $detail->receiving->tanggal_terima,
            $detail->receiving->nomor_penerimaan,
            $detail->receiving->purchaseOrder->nomor_po ?? '-',
            $detail->receiving->purchaseOrder->supplier->nama_supplier ?? 'Internal (Gudang/Pusat)',
            $detail->barang->part_code ?? '-',
            $detail->barang->part_name ?? '-',
            $detail->qty_terima,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'D97706']], // Orange gelap
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

                $sheet->getStyle('A1:G' . $lastRow)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => '000000']]],
                ]);
            },
        ];
    }
}