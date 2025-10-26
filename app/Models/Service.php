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
        'printed_at' => 'datetime',
    ];
    public function details()
    {
        return $this->hasMany(ServiceDetail::class);
    }
    public function lokasi() // Pastikan nama relasi ini benar
    {
        // Sesuaikan foreign key ('lokasi_id') dan owner key ('id') jika berbeda
        return $this->belongsTo(Lokasi::class, 'lokasi_id', 'id');
    }
}
