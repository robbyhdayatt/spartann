<?php

namespace App\Exports;

use App\Models\StockMovement;
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

class StockCardExport extends DefaultValueBinder implements 
    FromCollection, WithHeadings, WithMapping, ShouldAutoSize, 
    WithStyles, WithEvents, WithCustomValueBinder
{
    protected $barang_id;
    protected $lokasi_id;
    protected $start_date;
    protected $end_date;
    private $rowCount = 0;

    public function __construct($barang_id, $lokasi_id, $start_date, $end_date)
    {
        $this->barang_id = $barang_id;
        $this->lokasi_id = $lokasi_id;
        $this->start_date = $start_date;
        $this->end_date = $end_date;
    }

    public function bindValue(Cell $cell, $value)
    {
        // Kolom B = Kode Part
        if ($cell->getColumn() === 'B') {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        return parent::bindValue($cell, $value);
    }

    public function collection()
    {
        $query = StockMovement::where('barang_id', $this->barang_id)
            ->with(['barang', 'lokasi', 'user'])
            ->whereDate('created_at', '>=', $this->start_date)
            ->whereDate('created_at', '<=', $this->end_date);

        if ($this->lokasi_id) {
            $query->where('lokasi_id', $this->lokasi_id);
        }

        $data = $query->oldest()->get();
        $this->rowCount = $data->count();
        return $data;
    }

    public function headings(): array
    {
        return [
            'Tanggal Transaksi',
            'Kode Barang',
            'Nama Barang',
            'Lokasi Gudang',
            'Tipe Gerakan',
            'Qty Mutasi',
            'Stok Sebelum',
            'Stok Sesudah',
            'Keterangan / Referensi',
            'User Eksekutor',
        ];
    }

    public function map($movement): array
    {
        // Ubah format Tipe Gerakan agar lebih enak dibaca (Misal: App\Models\Penjualan menjadi PENJUALAN)
        $tipeGerakan = class_basename($movement->referensi_type);
        $tipeGerakan = strtoupper(preg_replace('/(?<!^)[A-Z]/', ' $0', $tipeGerakan)); // Tambah spasi sebelum huruf besar

        return [
            $movement->created_at->format('d-m-Y H:i'),
            $movement->barang->part_code ?? '-',
            $movement->barang->part_name ?? '-',
            $movement->lokasi->nama_lokasi ?? 'N/A',
            $tipeGerakan,
            $movement->jumlah,
            $movement->stok_sebelum == 0 ? '-' : $movement->stok_sebelum, // Sembunyikan jika 0
            $movement->stok_sesudah == 0 ? '-' : $movement->stok_sesudah, // Sembunyikan jika 0
            $movement->keterangan ?? '-',
            $movement->user->nama ?? 'Sistem',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => '1D4ED8'], // Biru gelap
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

                // Format Angka Tengah
                $sheet->getStyle('F2:H' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Border
                $sheet->getStyle('A1:J' . $lastRow)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => '000000']]],
                ]);
            },
        ];
    }
}