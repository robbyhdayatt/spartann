<?php

namespace App\Imports;

use App\Models\Part;
use App\Models\Brand;
use App\Models\Category;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithBatchInserts;

class PartsImport implements ToModel, WithHeadingRow, WithValidation, WithBatchInserts
{
    private $brands;
    private $categories;

    public function __construct()
    {
        $this->brands = Brand::all()->pluck('id', 'nama_brand')->mapWithKeys(function ($id, $name) {
            return [strtolower($name) => $id];
        });

        $this->categories = Category::all()->pluck('id', 'nama_kategori')->mapWithKeys(function ($id, $name) {
            return [strtolower($name) => $id];
        });
    }

    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        // Menggunakan kolom harga yang baru dan lookup ID yang benar
        return new Part([
            'kode_part'     => $row['kode_part'],
            'nama_part'     => $row['nama_part'],
            'brand_id'      => $this->brands->get(strtolower($row['brand'])), // Mengambil ID berdasarkan nama brand
            'category_id'   => $this->categories->get(strtolower($row['kategori'])), // Mengambil ID berdasarkan nama kategori
            'satuan'        => $row['satuan'],
            'stok_minimum'  => $row['stok_minimum'] ?? 0,
            'dpp'           => $row['dpp'],
            'ppn'           => $row['ppn'],
            'harga_satuan'  => $row['harga_satuan'],
        ]);
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        // Aturan validasi disesuaikan dengan kolom baru
        return [
            'kode_part' => 'required|unique:parts,kode_part',
            'nama_part' => 'required',
            'brand' => 'required|exists:brands,nama_brand',
            'kategori' => 'required|exists:categories,nama_kategori',
            'satuan' => 'required',
            'stok_minimum' => 'nullable|numeric',
            'dpp' => 'required|numeric',
            'ppn' => 'required|numeric',
            'harga_satuan' => 'required|numeric',
        ];
    }

    public function batchSize(): int
    {
        return 100; // Proses 100 baris sekali jalan untuk performa lebih baik
    }
}