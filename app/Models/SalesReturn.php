<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesReturn extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    protected $casts = ['tanggal_retur' => 'date'];

    public function details() { return $this->hasMany(SalesReturnDetail::class); }
    public function penjualan() { return $this->belongsTo(Penjualan::class); }
    public function konsumen() { return $this->belongsTo(Konsumen::class); }
    public function lokasi() { return $this->belongsTo(Lokasi::class, 'gudang_id'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }

    public static function generateReturnNumber()
    {
        $prefix = 'RTJ/' . now()->format('Ymd') . '/';
        $lastReturn = self::where('nomor_retur_jual', 'like', $prefix . '%')
                            ->orderBy('nomor_retur_jual', 'desc')
                            ->first();

        $number = $lastReturn ? ((int) substr($lastReturn->nomor_retur_jual, -4)) + 1 : 1;
        return $prefix . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}
