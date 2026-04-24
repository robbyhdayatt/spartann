<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PartsTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        // Hanya 3 kolom yang dibutuhkan di Excel
        return ['kode_part', 'nama_part', 'retail'];
    }

    public function array(): array
    {
        // Baris contoh pengisian
        return [
            ['123-ABC', 'CONTOH NAMA PART', 50000],
        ];
    }
}