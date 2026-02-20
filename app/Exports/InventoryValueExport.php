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
use Maatwebsite\Excel\Concerns\WithCustomValueBinder; 
use PhpOffice\PhpSpreadsheet\Cell\Cell;               
use PhpOffice\PhpSpreadsheet\Cell\DataType;           
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder; 
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class InventoryValueExport extends DefaultValueBinder implements
    FromCollection,
    WithHeadings,
    WithMapping,
    ShouldAutoSize,
    WithStyles,
    WithEvents,
    WithCustomValueBinder
{
    private $totalValue = 0;
    private $rowCount = 0;
    private $canSeeSellingIn;

    // Terima parameter dari controller
    public function __construct($canSeeSellingIn)
    {
        $this->canSeeSellingIn = $canSeeSellingIn;
    }

    // Paksa Format Text untuk Kode Barang
    public function bindValue(Cell $cell, $value)
    {
        if ($cell->getColumn() === 'B') {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        return parent::bindValue($cell, $value);
    }

    public function collection()
    {
        // Query menyesuaikan lokasi user saat ini
        $user = auth()->user();
        $query = InventoryBatch::with(['barang', 'lokasi', 'rak'])->where('quantity', '>', 0);
        
        if ($user->isPusat()) {
            $query->whereHas('lokasi', fn($q) => $q->where('tipe', 'DEALER'));
        } elseif ($user->isGudang() || $user->isDealer()) {
            $query->where('lokasi_id', $user->lokasi_id);
        }

        $data = $query->get()->sortBy('lokasi.nama_lokasi');
        $this->rowCount = $data->count();
        return $data;
    }

    public function headings(): array
    {
        // Nama kolom dinamis berdasarkan permission
        $hargaTeks = $this->canSeeSellingIn ? 'Harga Satuan (Selling In)' : 'Harga Satuan (Selling Out)';

        return [
            'Lokasi Gudang/Dealer',
            'Kode Barang',
            'Nama Barang',
            'Rak',
            'Stok Saat Ini',
            $hargaTeks,
            'Subtotal Nilai Aset',
        ];
    }

    public function map($batch): array
    {
        // Penentuan harga yang dipakai
        $harga = $this->canSeeSellingIn ? ($batch->barang->selling_in ?? 0) : ($batch->barang->selling_out ?? 0);
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
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => '4B5563'], // Abu-abu gelap
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
                $sheet = $event->sheet;
                $lastRow = $this->rowCount + 1;
                $totalRow = $lastRow + 1;

                // Format Ribuan (Stok)
                $sheet->getStyle('E2:E' . $totalRow)->getNumberFormat()->setFormatCode('#,##0');
                // Format Rupiah (Harga, Subtotal)
                $sheet->getStyle('F2:G' . $totalRow)->getNumberFormat()->setFormatCode('_("Rp"* #,##0_);_("Rp"* (#,##0);_("Rp"* "-"_);_(@_)');

                // Footer Total
                $sheet->setCellValue('A' . $totalRow, 'TOTAL KESELURUHAN ASET');
                $sheet->mergeCells('A' . $totalRow . ':F' . $totalRow);
                $sheet->setCellValue('G' . $totalRow, $this->totalValue);

                // Styling Baris Total
                $sheet->getStyle('A' . $totalRow . ':G' . $totalRow)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'E5E7EB'] // Abu-abu terang
                    ],
                ]);
                $sheet->getStyle('A' . $totalRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Border Seluruh Tabel
                $sheet->getStyle('A1:G' . $totalRow)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => '000000']]],
                ]);
            },
        ];
    }
}