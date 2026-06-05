<?php

namespace App\Imports;

use App\Models\Part;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class PartsImport implements ToCollection, WithHeadingRow, WithChunkReading 
{
    public function collection(Collection $rows)
    {
        $upsertData = [];

        foreach ($rows as $row) {
            // 1. Abaikan baris jika Kode Part kosong di Excel
            if (!isset($row['kode_part']) || empty(trim($row['kode_part']))) {
                continue; 
            }

            // 2. Siapkan keranjang data (Persiapan Bulk Insert/Update)
            $upsertData[] = [
                'kode_part'    => trim($row['kode_part']),
                'nama_part'    => isset($row['nama_part']) ? trim($row['nama_part']) : '-',
                'cost'         => isset($row['cost']) ? (float) $row['cost'] : 0,
                'retail'       => isset($row['retail']) ? (float) $row['retail'] : 0,
                // Nilai default di bawah ini HANYA dipakai jika barang tersebut adalah data BARU.
                // Jika barang sudah ada, upsert() tidak akan menyentuh kolom stok ini.
                'stok_minimum' => 10,
                'qty_stok'     => 0,
                'is_active'    => true,
            ];
        }

        // 3. Eksekusi Smart Bulk Upsert
        if (!empty($upsertData)) {
            Part::upsert(
                $upsertData,
                ['kode_part'], // Parameter Acuan/Filter: Cek kesamaan berdasarkan KODE PART
                ['nama_part', 'cost', 'retail'] // Parameter Update: Jika kode_part sudah ada, HANYA update Nama, Modal & Retail
            );
        }
    }

    public function chunkSize(): int
    {
        return 1000; 
    }
}