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
use Illuminate\Support\Facades\DB;

class StockByWarehouseExport extends DefaultValueBinder implements
    FromCollection,
    WithHeadings,
    WithMapping,
    ShouldAutoSize,
    WithStyles,
    WithEvents,
    WithCustomValueBinder // Tambahan Interface
{
    protected $lokasi_id;
    private $rowCount = 0;

    public function __construct($lokasi_id)
    {
        $this->lokasi_id = $lokasi_id;
    }

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
        $data = InventoryBatch::select(
                'barang_id',
                'rak_id',
                'lokasi_id',
                DB::raw('SUM(quantity) as quantity')
            )
            ->where('lokasi_id', $this->lokasi_id)
            ->where('quantity', '>', 0)
            ->with(['barang', 'rak', 'lokasi'])
            ->groupBy('barang_id', 'rak_id', 'lokasi_id')
            ->get()
            ->sortBy('barang.part_name');

        $this->rowCount = $data->count();
        return $data;
    }

    public function headings(): array
    {
        return [
            'Lokasi',
            'Kode Barang', // Kolom B
            'Nama Barang',
            'Merk',
            'Rak',
            'Selling In',
            'Selling Out',
            'Retail',
            'Stok',
        ];
    }

    public function map($item): array
    {
        return [
            $item->lokasi->nama_lokasi ?? '-',
            $item->barang->part_code ?? '-',
            $item->barang->part_name ?? '-',
            $item->barang->merk ?? '-',
            $item->rak->kode_rak ?? '-',
            $item->barang->selling_in ?? 0,
            $item->barang->selling_out ?? 0,
            $item->barang->retail ?? 0,
            $item->quantity,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $lastRow = $this->rowCount + 1;

                // Format Rupiah (F, G, H)
                $sheet->getStyle('F2:H' . $lastRow)
                      ->getNumberFormat()
                      ->setFormatCode('_("Rp"* #,##0_);_("Rp"* (#,##0);_("Rp"* "-"_);_(@_)');

                // Border Tabel
                $sheet->getStyle('A1:I' . $lastRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                    ],
                ]);
            },
        ];
    }
}
