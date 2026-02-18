<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Barang extends Model
{
    use HasFactory;

    protected $table = 'barangs';
    protected $guarded = ['id']; // Aman, is_active otomatis masuk

    protected $casts = [
        'selling_in'  => 'decimal:2',
        'selling_out' => 'decimal:2',
        'retail'      => 'decimal:2',
        'is_active'   => 'boolean', // [BARU] Casting ke boolean
    ];

    // --- Relasi WMS & Transaksi (TETAP SAMA) ---

    public function inventoryBatches()
    {
        return $this->hasMany(InventoryBatch::class, 'barang_id');
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class, 'barang_id');
    }

    public function penjualanDetails()
    {
        return $this->hasMany(PenjualanDetail::class, 'barang_id');
    }

    public function purchaseOrderDetails()
    {
        return $this->hasMany(PurchaseOrderDetail::class, 'barang_id');
    }

    // --- Helper Methods (TETAP SAMA) ---

    /**
     * Menghitung total stok aktif (quantity > 0)
     */
    public function getTotalStockAttribute()
    {
        return $this->inventoryBatches()->sum('quantity');
    }

    /**
     * Menghitung stok spesifik per lokasi
     */
    public function getStockByLokasi($lokasiId)
    {
        return $this->inventoryBatches()
                    ->where('lokasi_id', $lokasiId)
                    ->sum('quantity');
    }
    
    // --- Scopes (BARU) ---
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}