<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StockMutation extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'approved_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function part()
    {
        return $this->belongsTo(Part::class);
    }

    public function lokasiAsal()
    {
        return $this->belongsTo(Lokasi::class, 'lokasi_asal_id');
    }

    public function lokasiTujuan()
    {
        return $this->belongsTo(Lokasi::class, 'lokasi_tujuan_id');
    }

    public function rakAsal()
    {
        return $this->belongsTo(Rak::class, 'rak_asal_id');
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

    public function rakTujuan()
    {
        return $this->belongsTo(Rak::class, 'rak_tujuan_id');
    }

    public static function generateNomorMutasi()
    {
        $prefix = 'MT-' . date('Ymd') . '-';
        $lastMutation = self::where('nomor_mutasi', 'like', $prefix . '%')->latest('id')->first();
        $newNumber = $lastMutation ? ((int) substr($lastMutation->nomor_mutasi, -4)) + 1 : 1;
        return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }
}
