<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMutation extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'approved_at' => 'datetime',
        'received_at' => 'datetime',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function lokasiAsal()
    {
        return $this->belongsTo(Lokasi::class, 'lokasi_asal_id');
    }

    public function lokasiTujuan()
    {
        return $this->belongsTo(Lokasi::class, 'lokasi_tujuan_id');
    }

    // Relasi Rak Asal (Mungkin NULL jika mutasi belum diproses atau diambil dari banyak rak)
    // Sebaiknya tidak diandalkan untuk logika FIFO, gunakan StockMovement untuk tracking detail rak
    public function rakAsal()
    {
        return $this->belongsTo(Rak::class, 'rak_asal_id');
    }
    
    public function rakTujuan()
    {
        return $this->belongsTo(Rak::class, 'rak_tujuan_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public static function generateNomorMutasi()
    {
        // Format: MT-YYYYMMDD-0001
        $prefix = 'MT-' . date('Ymd') . '-';
        $lastMutation = self::where('nomor_mutasi', 'like', $prefix . '%')->latest('id')->first();
        $newNumber = $lastMutation ? ((int) substr($lastMutation->nomor_mutasi, -4)) + 1 : 1;
        return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }
}