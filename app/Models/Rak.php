<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rak extends Model
{
    use HasFactory;

    protected $fillable = [
        'lokasi_id', 
        'kode_rak', // Ini akan digenerate otomatis
        'nama_rak', // Ini juga digenerate otomatis
        'zona', 
        'nomor_rak', 
        'level', 
        'bin', 
        'tipe_rak', 
        'is_active'
    ];

    // Boot function untuk auto-generate kode sebelum simpan
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($rak) {
            // Format: A-R03-L2-B05
            $fullCode = sprintf(
                "%s-%s-%s-%s",
                strtoupper($rak->zona),
                strtoupper($rak->nomor_rak),
                strtoupper($rak->level),
                strtoupper($rak->bin)
            );
            
            $rak->kode_rak = $fullCode;
            $rak->nama_rak = $fullCode; // Nama rak disamakan dengan kode biar mudah
        });
    }

    public function lokasi()
    {
        return $this->belongsTo(Lokasi::class, 'lokasi_id');
    }
    
    public function inventoryBatches()
    {
        return $this->hasMany(InventoryBatch::class);
    }
}