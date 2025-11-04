<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Barang extends Model
{
    use HasFactory;

    /**
     * Tentukan nama tabel karena nama model (Barang)
     * tidak jamak (plural) menjadi (Barangs).
     */
    protected $table = 'barangs';

    /**
     * Izinkan semua kolom diisi kecuali 'id'.
     */
    protected $guarded = ['id'];

    /**
     * Tentukan tipe data untuk kolom desimal.
     */
    protected $casts = [
        'harga_modal' => 'decimal:2',
        'harga_jual' => 'decimal:2',
    ];

    /**
     * Relasi ke converts_main (satu Barang bisa dipakai di banyak paket convert)
     */
    public function convertsMain()
    {
        // Relasi ke tabel 'converts_main' melalui kolom 'part_code'
        return $this->hasMany(Convert::class, 'part_code', 'part_code');
    }

    public function penjualanDetails()
    {
        return $this->hasMany(PenjualanDetail::class);
    }
}
