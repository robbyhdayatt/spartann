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

    /**
     * PERBAIKAN: Relasi ke Lokasi harus menggunakan foreign key 'lokasi_id'
     * dan terhubung ke primary key 'id' di tabel lokasi.
     *
     * Asumsi: tabel 'services' memiliki kolom 'lokasi_id'.
     */
    public function lokasi()
    {
        // Gunakan relasi yang sudah standar berbasis ID
        return $this->belongsTo(Lokasi::class, 'lokasi_id');
    }
}