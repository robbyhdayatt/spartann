<?php

namespace App\Exports;

use App\Models\Inventory;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StockByWarehouseExport implements FromCollection, WithHeadings, WithMapping
{
    protected $lokasi_id;

    public function __construct(int $lokasi_id)
    {
        $this->lokasi_id = $lokasi_id;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return Inventory::where('lokasi_id', $this->lokasi_id)
            ->with(['part', 'rak', 'lokasi'])
            ->where('quantity', '>', 0)
            ->get();
    }

    /**
    * @return array
    */
    public function headings(): array
    {
        return [
            'lokasi',
            'Kode Part',
            'Nama Part',
            'Kode Rak',
            'Nama Rak',
            'Jumlah Stok',
        ];
    }

    /**
    * @param mixed $inventory
    * @return array
    */
    public function map($inventory): array
    {
        return [
            $inventory->lokasi->nama_lokasi,
            $inventory->part->kode_part,
            $inventory->part->nama_part,
            $inventory->rak->kode_rak,
            $inventory->rak->nama_rak,
            $inventory->quantity,
        ];
    }
}
