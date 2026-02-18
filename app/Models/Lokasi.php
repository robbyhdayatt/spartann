<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lokasi extends Model
{
    use HasFactory;

    protected $table = 'lokasi';
    
    // Konstanta Tipe
    const TIPE_PUSAT  = 'PUSAT';
    const TIPE_GUDANG = 'GUDANG';
    const TIPE_DEALER = 'DEALER';

    protected $fillable = [
        'kode_lokasi',
        'nama_lokasi',
        'singkatan',
        'npwp',
        'alamat',
        'tipe',
        'koadmin',  
        'asd',      
        'aom',      
        'asm',      
        'gm',       
        'is_active',
    ];

    public function raks()
    {
        return $this->hasMany(Rak::class, 'lokasi_id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'lokasi_id');
    }
    
    // Scope Helpers
    public function scopePusat($query) { return $query->where('tipe', self::TIPE_PUSAT); }
    public function scopeGudang($query) { return $query->where('tipe', self::TIPE_GUDANG); }
    public function scopeDealer($query) { return $query->where('tipe', self::TIPE_DEALER); }
}