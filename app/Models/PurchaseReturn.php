<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturn extends Model
{
    use HasFactory;
    
    protected $guarded = ['id'];

    protected $casts = [
        'tanggal_retur' => 'date',
    ];

    public function details()
    {
        return $this->hasMany(PurchaseReturnDetail::class);
    }

    public function receiving()
    {
        return $this->belongsTo(Receiving::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateReturnNumber()
    {
        // Format: RTB/YYYYMMDD/0001 (RTB = Retur Beli)
        $prefix = 'RTB/' . now()->format('Ymd') . '/';
        $last = self::where('nomor_retur', 'like', $prefix . '%')
                    ->orderBy('nomor_retur', 'desc')
                    ->first();

        $seq = $last ? ((int) substr($last->nomor_retur, -4)) + 1 : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}