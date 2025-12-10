<?php

namespace App\Exports;

use App\Models\PenjualanDetail;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class SalesSummaryExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithColumnFormatting, WithEvents
{
    protected $startDate;
    protected $endDate;
    protected $dealerId;

    // Variabel untuk menampung total perhitungan
    protected $totalQty = 0;
    protected $totalPenjualan = 0;
    protected $totalHpp = 0;
    protected $totalProfit = 0;
    protected $rowCount = 0; // Menghitung baris data

    public function __construct($startDate, $endDate, $dealerId)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->dealerId = $dealerId;
    }

    public function collection()
    {
        return PenjualanDetail::with(['penjualan.lokasi', 'penjualan.konsumen', 'penjualan.sales', 'barang'])
            ->whereHas('penjualan', function ($q) {
                $q->whereBetween('tanggal_jual', [$this->startDate, $this->endDate]);
                if ($this->dealerId) {
                    $q->where('lokasi_id', $this->dealerId);
                }
            })
            ->orderByDesc('created_at')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'No. Faktur',
            'Dealer/Lokasi',
            'Konsumen',
            'Sales',
            'Kode Barang',
            'Nama Barang',
            'Qty',
            'Harga Jual Satuan',
            'Total Penjualan',
            'HPP Satuan',
            'Total HPP',
            'Profit',
        ];
    }

    public function map($detail): array
    {
        // Gunakan selling_out sesuai perbaikan logika sebelumnya
        $hpp = $detail->barang->selling_out ?? 0;
        $totalHppItem = $detail->qty_jual * $hpp;
        $profitItem = $detail->subtotal - $totalHppItem;

        // Akumulasi untuk Grand Total
        $this->totalQty += $detail->qty_jual;
        $this->totalPenjualan += $detail->subtotal;
        $this->totalHpp += $totalHppItem;
        $this->totalProfit += $profitItem;
        $this->rowCount++; // Tambah counter baris

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
            $hpp,
            $totalHppItem,
            $profitItem,
        ];
    }

    // Styling dasar untuk Header
    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => '4B5563'], // Warna Abu-abu Gelap
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    // Format Angka (Ribuan)
    public function columnFormats(): array
    {
        return [
            'H' => '#,##0', // Qty
            'I' => '#,##0', // Harga Jual
            'J' => '#,##0', // Total Jual
            'K' => '#,##0', // HPP
            'L' => '#,##0', // Total HPP
            'M' => '#,##0', // Profit
        ];
    }

    // Event untuk menambahkan baris Grand Total & Border di akhir
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                // Menentukan posisi baris terakhir data dan baris total
                $lastDataRow = $this->rowCount + 1; // +1 karena ada header
                $totalRow = $lastDataRow + 1;

                // === 1. Menambahkan Baris Grand Total ===
                $sheet->setCellValue('A' . $totalRow, 'GRAND TOTAL');
                $sheet->mergeCells('A' . $totalRow . ':G' . $totalRow); // Merge kolom A sampai G
                
                // Isi Nilai Total
                $sheet->setCellValue('H' . $totalRow, $this->totalQty);
                $sheet->setCellValue('J' . $totalRow, $this->totalPenjualan);
                $sheet->setCellValue('L' . $totalRow, $this->totalHpp);
                $sheet->setCellValue('M' . $totalRow, $this->totalProfit);

                // Styling Baris Grand Total (Bold & Background Abu muda)
                $sheet->getStyle('A' . $totalRow . ':M' . $totalRow)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'E5E7EB'], 
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                
                // Align Text "GRAND TOTAL" ke Kanan
                $sheet->getStyle('A' . $totalRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Format Angka di Baris Total
                $sheet->getStyle('H' . $totalRow . ':M' . $totalRow)->getNumberFormat()->setFormatCode('#,##0');

                // === 2. Menambahkan Border ke Seluruh Tabel ===
                $styleBorder = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => '000000'],
                        ],
                    ],
                ];
                // Terapkan border dari A1 sampai M baris terakhir (Total)
                $sheet->getStyle('A1:M' . $totalRow)->applyFromArray($styleBorder);
            },
        ];
    }
}