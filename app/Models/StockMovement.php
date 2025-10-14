<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    public function part()
    {
        return $this->belongsTo(Part::class);
    }

    public function lokasi()
    {
        return $this->belongsTo(Lokasi::class, 'gudang_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function rak()
    {
        return $this->belongsTo(Rak::class);
    }

    /**
     * Get the parent referensi model (PurchaseOrder, Penjualan, etc.).
     */
    public function referensi()
    {
        return $this->morphTo();
    }
}
