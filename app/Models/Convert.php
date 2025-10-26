<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Convert extends Model
{
    use HasFactory;

    // Izinkan semua kolom diisi kecuali 'id'
    protected $guarded = ['id'];

    /**
     * Tentukan tipe data untuk kolom desimal agar otomatis di-cast.
     * Sesuaikan jika tipe data di database Anda berbeda (misal, float).
     */
    protected $casts = [
        'harga_modal' => 'decimal:2',
        'harga_jual' => 'decimal:2',
    ];
}