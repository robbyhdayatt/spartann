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
        'barang_id',
        'rak_id',      // <-- Pastikan ini ada
        'qty_jual',
        'harga_jual',
        'subtotal',
        'qty_diretur'
    ];

    // Relasi ke Rak (Agar nama rak bisa muncul di laporan)
    public function rak()
    {
        return $this->belongsTo(Rak::class, 'rak_id');
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function penjualan()
    {
        return $this->belongsTo(Penjualan::class, 'penjualan_id');
    }
}