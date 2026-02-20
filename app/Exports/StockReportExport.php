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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Illuminate\Support\Facades\DB;

class StockReportExport extends DefaultValueBinder implements
    FromCollection, WithHeadings, WithMapping, ShouldAutoSize, 
    WithStyles, WithEvents, WithCustomValueBinder
{
    private $rowCount = 0;
    private $colCount = 0;
    protected $canSeeSellingIn;
    protected $canSeeSellingOut;

    public function __construct($canSeeSellingIn, $canSeeSellingOut)
    {
        $this->canSeeSellingIn = $canSeeSellingIn;
        $this->canSeeSellingOut = $canSeeSellingOut;
    }

    public function bindValue(Cell $cell, $value)
    {
        // Kolom A adalah Kode Barang (Paksa jadi text)
        if ($cell->getColumn() === 'A') {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        return parent::bindValue($cell, $value);
    }

    public function collection()
    {
        $data = InventoryBatch::select(
                'barang_id',
                'lokasi_id',
                'rak_id',
                DB::raw('SUM(quantity) as quantity')
            )
            ->where('quantity', '>', 0)
            ->with(['barang', 'lokasi', 'rak'])
            ->groupBy('barang_id', 'lokasi_id', 'rak_id')
            ->get()
            ->sortBy(['barang.part_name', 'lokasi.nama_lokasi']);

        $this->rowCount = $data->count();
        return $data;
    }

    public function headings(): array
    {
        $headers = [
            'Kode Barang', // Kolom A
            'Nama Barang',
            'Merk',
            'Lokasi Gudang / Dealer',
            'Kode Rak',
        ];

        // Tambah header dinamis sesuai permission
        if ($this->canSeeSellingIn) { $headers[] = 'Harga Modal (Selling In)'; }
        if ($this->canSeeSellingOut) { 
            $headers[] = 'Harga Jual (Selling Out)'; 
            $headers[] = 'Harga Retail (HET)'; 
        }
        
        $headers[] = 'Total Stok';
        
        $this->colCount = count($headers); // Simpan jumlah kolom untuk styling
        return $headers;
    }

    public function map($item): array
    {
        $row = [
            $item->barang->part_code ?? '-',
            $item->barang->part_name ?? '-',
            $item->barang->merk ?? '-',
            $item->lokasi->nama_lokasi ?? '-',
            $item->rak->kode_rak ?? '-',
        ];

        // Tambah data dinamis sesuai permission
        if ($this->canSeeSellingIn) { $row[] = $item->barang->selling_in ?? 0; }
        if ($this->canSeeSellingOut) { 
            $row[] = $item->barang->selling_out ?? 0; 
            $row[] = $item->barang->retail ?? 0; 
        }

        $row[] = $item->quantity;

        return $row;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => '2563EB']], // Biru Medium
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $lastRow = $this->rowCount + 1;
                $lastColLetter = Coordinate::stringFromColumnIndex($this->colCount);

                // Styling Border Semua
                $sheet->getStyle('A1:' . $lastColLetter . $lastRow)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => '000000']]],
                ]);

                // Format Angka Ribuan untuk Stok (Kolom Paling Akhir)
                $sheet->getStyle($lastColLetter . '2:' . $lastColLetter . $lastRow)
                      ->getNumberFormat()->setFormatCode('#,##0');

                // Jika ada kolom harga, format dengan Rupiah. (Kolom antara Rak dan Stok)
                if ($this->colCount > 6) {
                    $firstPriceCol = Coordinate::stringFromColumnIndex(6); // Kolom ke-6 (F)
                    $lastPriceCol = Coordinate::stringFromColumnIndex($this->colCount - 1); // Sebelum stok
                    $sheet->getStyle($firstPriceCol . '2:' . $lastPriceCol . $lastRow)
                          ->getNumberFormat()->setFormatCode('_("Rp"* #,##0_);_("Rp"* (#,##0);_("Rp"* "-"_);_(@_)');
                }
            },
        ];
    }
}