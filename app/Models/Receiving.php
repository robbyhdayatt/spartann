<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Receiving extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $casts = [
        'tanggal_terima' => 'date',
        'qc_at' => 'datetime',
        'putaway_at' => 'datetime',
    ];

    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class); }
    public function lokasi() { return $this->belongsTo(Lokasi::class, 'lokasi_id'); }
    public function details() { return $this->hasMany(ReceivingDetail::class); }
    public function receivedBy() { return $this->belongsTo(User::class, 'received_by'); }
    public function stockMovements() { return $this->morphMany(StockMovement::class, 'referensi'); }

    public static function generateReceivingNumber()
    {
        $date = now()->format('Ymd');
        $count = self::whereDate('created_at', today())->count() + 1;
        return "RCV/{$date}/" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}