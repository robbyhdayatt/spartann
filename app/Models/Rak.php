<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rak extends Model
{
    use HasFactory;
    protected $fillable = [
        'gudang_id', 'kode_rak', 'nama_rak', 'tipe_rak', 'is_active'
    ];

    public function lokasi()
    {
        return $this->belongsTo(Lokasi::class, 'gudang_id');
    }
}
