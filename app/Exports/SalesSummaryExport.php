<?php

namespace App\Exports;

use App\Models\PenjualanDetail;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class SalesSummaryExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $startDate;
    protected $endDate;
    protected $dealerId;

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
        $hpp = $detail->barang->selling_in ?? 0;
        $totalHpp = $detail->qty_jual * $hpp;
        $profit = $detail->subtotal - $totalHpp;

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
            $totalHpp,
            $profit,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
