<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lokasi extends Model
{
    use HasFactory;

    protected $table = 'lokasi';

    protected $fillable = [
        'kode_lokasi',
        'nama_lokasi',
        'npwp',
        'alamat',
        'tipe',
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

    public function dealer()
    {
        return $this->belongsTo(Dealer::class, 'kode_lokasi', 'kode_dealer');
    }
}
