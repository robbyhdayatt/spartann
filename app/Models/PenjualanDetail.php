<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PenjualanDetail extends Model
{
    use HasFactory;
    protected $guarded = ['id']; // $guarded = ['id'] sudah OK

    /**
     * Get the main sales transaction that this detail belongs to.
     */
    public function penjualan()
    {
        return $this->belongsTo(Penjualan::class);
    }

    // /**
    //  * Relasi opsional ke Part (jika penjualan lama masih pakai)
    //  */
    // public function part()
    // {
    //     return $this->belongsTo(Part::class);
    // }

    // /**
    //  * Relasi opsional ke Rak (jika penjualan lama masih pakai)
    //  */
    // public function rak()
    // {
    //     return $this->belongsTo(Rak::class);
    // }

    /**
     * ++ BARU: Relasi ke item Barang ++
     */
    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }
}
