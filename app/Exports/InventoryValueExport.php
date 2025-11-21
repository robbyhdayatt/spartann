<?php

namespace App\Exports;

use App\Models\InventoryBatch;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder; // Tambahan
use PhpOffice\PhpSpreadsheet\Cell\Cell;               // Tambahan
use PhpOffice\PhpSpreadsheet\Cell\DataType;           // Tambahan
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder; // Tambahan
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class InventoryValueExport extends DefaultValueBinder implements
    FromCollection,
    WithHeadings,
    WithMapping,
    ShouldAutoSize,
    WithStyles,
    WithEvents,
    WithCustomValueBinder // Tambahan Interface
{
    private $totalValue = 0;
    private $rowCount = 0;

    // ++ PERBAIKAN: Paksa Format Text untuk Kode Barang ++
    public function bindValue(Cell $cell, $value)
    {
        // Kolom B adalah Kode Barang
        if ($cell->getColumn() === 'B') {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function collection()
    {
        $data = InventoryBatch::with(['barang', 'lokasi', 'rak'])
            ->where('quantity', '>', 0)
            ->get();

        $this->rowCount = $data->count();
        return $data;
    }

    public function headings(): array
    {
        return [
            'Lokasi',
            'Kode Barang', // Kolom B
            'Nama Barang',
            'Rak',
            'Stok Saat Ini',
            'Harga Satuan (Selling Out)',
            'Subtotal Nilai Aset',
        ];
    }

    public function map($batch): array
    {
        $harga = $batch->barang->selling_out ?? 0;
        $subtotal = $batch->quantity * $harga;
        $this->totalValue += $subtotal;

        return [
            $batch->lokasi->nama_lokasi ?? '-',
            $batch->barang->part_code ?? '-',
            $batch->barang->part_name ?? '-',
            $batch->rak->kode_rak ?? '-',
            $batch->quantity,
            $harga,
            $subtotal,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $lastRow = $this->rowCount + 1;
                $totalRow = $lastRow + 1;

                // Format Rupiah (F, G)
                $sheet->getStyle('F2:G' . $totalRow)
                      ->getNumberFormat()
                      ->setFormatCode('_("Rp"* #,##0_);_("Rp"* (#,##0);_("Rp"* "-"_);_(@_)');

                // Border Tabel
                $sheet->getStyle('A1:G' . $lastRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                    ],
                ]);

                // Footer Total
                $sheet->setCellValue('A' . $totalRow, 'TOTAL NILAI PERSEDIAAN');
                $sheet->mergeCells('A' . $totalRow . ':F' . $totalRow);
                $sheet->setCellValue('G' . $totalRow, $this->totalValue);

                $sheet->getStyle('A' . $totalRow . ':G' . $totalRow)->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFFFFF00']
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                    ],
                ]);
                $sheet->getStyle('A' . $totalRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
