<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lokasi extends Model
{
    use HasFactory;

    protected $table = 'lokasi';
    protected $guarded = ['id'];

    public function raks()
    {
        return $this->hasMany(Rak::class, 'lokasi_id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'lokasi_id');
    }

    // Di dalam App\Models\Lokasi.php
    public function dealer()
    {
        // Sesuaikan 'kode_dealer' jika nama kolom di tabel dealers berbeda
        // Sesuaikan 'kode_lokasi' jika nama kolom di tabel lokasi berbeda
        return $this->belongsTo(Dealer::class, 'kode_lokasi', 'kode_dealer');
    }
}
