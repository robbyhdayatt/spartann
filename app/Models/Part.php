<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Part extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function inventoryBatches()
    {
        return $this->hasMany(InventoryBatch::class);
    }

    public function penjualanDetails()
    {
        return $this->hasMany(PenjualanDetail::class);
    }

    public function purchaseOrderDetails()
    {
        return $this->hasMany(PurchaseOrderDetail::class);
    }

    /**
     * Menghitung total stok part di gudang tertentu.
     *
     * @param int $lokasiId
     * @return int
     */
    public function getStockByGudang($lokasiId)
    {
        return $this->inventoryBatches()
                    ->where('lokasi_id', $lokasiId)
                    ->sum('quantity');
    }
}
