<?php

namespace App\Exports;

use App\Models\InventoryBatch;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Illuminate\Support\Facades\DB;

class StockReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function collection()
    {
        return InventoryBatch::select(
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
    }

    public function headings(): array
    {
        return [
            'Kode Barang',
            'Nama Barang',
            'Merk',
            'Lokasi',
            'Rak',
            'Stok',
        ];
    }

    public function map($item): array
    {
        return [
            $item->barang->part_code ?? '-',
            $item->barang->part_name ?? '-',
            $item->barang->merk ?? '-',
            $item->lokasi->nama_lokasi ?? '-',
            $item->rak->kode_rak ?? '-',
            $item->quantity,
        ];
    }
}
