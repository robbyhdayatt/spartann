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

    // ++ TAMBAHKAN FUNGSI INI ++
    /**
     * Menghitung total stok part di gudang tertentu.
     *
     * @param int $gudangId
     * @return int
     */
    public function getStockByGudang($gudangId)
    {
        // Menjumlahkan 'quantity' dari semua batch yang cocok
        // dengan part ini dan gudang yang diberikan.
        return $this->inventoryBatches()
                    ->where('gudang_id', $gudangId)
                    ->sum('quantity');
    }
}