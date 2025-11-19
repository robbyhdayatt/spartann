<?php

namespace App\Exports;

use App\Models\InventoryBatch; // Ganti Inventory
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class InventoryValueExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return InventoryBatch::with(['barang', 'lokasi', 'rak']) // Ganti part
            ->where('quantity', '>', 0)
            ->get();
    }

    public function headings(): array
    {
        return [
            'Lokasi',
            'Kode Barang',
            'Nama Barang',
            'Rak',
            'Stok Saat Ini',
            'Harga Beli/Modal (Rp)', // selling_in
            'Subtotal Nilai Aset (Rp)',
        ];
    }

    public function map($batch): array
    {
        // Gunakan selling_in dari master barang
        $hargaBeli = $batch->barang->selling_in ?? 0;
        $subtotal = $batch->quantity * $hargaBeli;

        return [
            $batch->lokasi->nama_lokasi ?? '-',
            $batch->barang->part_code ?? '-',
            $batch->barang->part_name ?? '-',
            $batch->rak->kode_rak ?? '-',
            $batch->quantity,
            $hargaBeli,
            $subtotal,
        ];
    }
}
