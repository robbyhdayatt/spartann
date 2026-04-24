<?php

namespace App\Imports;

use App\Models\Part;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class PartsImport implements ToModel, WithHeadingRow, WithChunkReading 
{
    public function model(array $row)
    {
        if (!isset($row['kode_part']) || empty($row['kode_part'])) {
            return null; 
        }

        $part = Part::where('kode_part', $row['kode_part'])->first();

        if ($part) {
            $part->update([
                'nama_part' => $row['nama_part'] ?? $part->nama_part,
                'retail'    => $row['retail'] ?? $part->retail,
            ]);
            return $part;
        } else {
            return Part::create([
                'kode_part'    => $row['kode_part'],
                'nama_part'    => $row['nama_part'] ?? '-',
                'retail'       => $row['retail'] ?? 0,
                'stok_minimum' => 10,
                'qty_stok'     => 0,
                'is_active'    => true,
            ]);
        }
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}