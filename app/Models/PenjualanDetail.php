<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PenjualanDetail extends Model
{
    use HasFactory;

    protected $table = 'penjualan_details';

    protected $fillable = [
        'penjualan_id',
        'barang_id',    // PENTING: Referensi utama barang sekarang
        'qty_jual',
        'harga_jual',
        'subtotal',     // Kolom Baru
        
        // Kolom Legacy (Tetap didaftarkan agar tidak error jika ada kode lama yang pakai)
        'convert_id', 
        'part_id',
        'rak_id',
        'qty_diretur'
    ];

    // --- RELASI ---

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function penjualan()
    {
        return $this->belongsTo(Penjualan::class, 'penjualan_id');
    }
}