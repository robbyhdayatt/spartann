<?php

namespace App\Exports;

use App\Models\StockMovement;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class StockCardExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $barang_id; // Ganti part_id
    protected $lokasi_id;
    protected $start_date;
    protected $end_date;

    public function __construct($barang_id, $lokasi_id, $start_date, $end_date)
    {
        $this->barang_id = $barang_id;
        $this->lokasi_id = $lokasi_id;
        $this->start_date = $start_date;
        $this->end_date = $end_date;
    }

    public function collection()
    {
        // Query Barang ID
        $query = StockMovement::where('barang_id', $this->barang_id)
            ->with(['barang', 'lokasi', 'user']) // Ganti part
            ->whereDate('created_at', '>=', $this->start_date)
            ->whereDate('created_at', '<=', $this->end_date);

        if ($this->lokasi_id) {
            $query->where('lokasi_id', $this->lokasi_id);
        }

        return $query->oldest()->get();
    }

    public function headings(): array
    {
        return [
            'Barang', // Ganti Part
            'Tanggal',
            'Lokasi',
            'Tipe Gerakan',
            'Jumlah',
            'Stok Sebelum',
            'Stok Sesudah',
            'Referensi',
            'User',
        ];
    }

    public function map($movement): array
    {
        return [
            $movement->barang->part_name . ' (' . $movement->barang->part_code . ')',
            $movement->created_at->format('d-m-Y H:i'),
            $movement->lokasi->nama_lokasi ?? 'N/A',
            str_replace('_', ' ', $movement->tipe_gerakan),
            $movement->jumlah,
            $movement->stok_sebelum,
            $movement->stok_sesudah,
            $movement->referensi,
            $movement->user->nama ?? 'Sistem',
        ];
    }
}
