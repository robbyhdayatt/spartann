<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceDetail extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Relasi ke Barang (Pengganti Part)
     */
    public function barang()
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }
}
