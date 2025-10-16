<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'reg_date' => 'date',
    ];
    public function details()
    {
        return $this->hasMany(ServiceDetail::class);
    }
    public function lokasi()
    {
        return $this->belongsTo(Lokasi::class, 'dealer_code', 'kode_gudang');
    }
}
