<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Penjualan extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    protected $casts = [ 'tanggal_jual' => 'date', ];

    public function details()
    {
        return $this->hasMany(PenjualanDetail::class);
    }

    public function konsumen()
    {
        return $this->belongsTo(Konsumen::class);
    }

    public function lokasi()
    {
        return $this->belongsTo(Lokasi::class, 'lokasi_id');
    }

    public function sales()
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public static function generateNomorFaktur()
    {
        $prefix = 'INV/' . date('Ym') . '/';
        $lastSale = DB::table('penjualans')
                        ->where('nomor_faktur', 'like', $prefix . '%')
                        ->orderBy('nomor_faktur', 'desc')
                        ->first();

        $newNumber = $lastSale ? ((int) substr($lastSale->nomor_faktur, -4)) + 1 : 1;
        return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    public function stockMovements()
    {
        return $this->morphMany(StockMovement::class, 'referensi');
    }
}
