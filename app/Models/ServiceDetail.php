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

    public function partData()
    {
        // Relasi ke model Part: (Nama Model, 'foreign_key_di_tabel_ini', 'primary_key_di_tabel_tujuan')
        return $this->belongsTo(Part::class, 'item_code', 'kode_part');
    }
}
