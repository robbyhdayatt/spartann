<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PartsTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        // Penambahan kolom cost (Harga Modal)
        return ['kode_part', 'nama_part', 'cost', 'retail'];
    }

    public function array(): array
    {
        // Baris contoh pengisian
        return [
            ['123-ABC', 'CONTOH NAMA PART', 40000, 50000],
        ];
    }
}