<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function lokasi()
    {
        return $this->belongsTo(Lokasi::class, 'lokasi_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function rak()
    {
        return $this->belongsTo(Rak::class);
    }

    public function referensi()
    {
        return $this->morphTo();
    }
}
