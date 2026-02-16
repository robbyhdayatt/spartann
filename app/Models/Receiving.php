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

    // Relations
    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class); }
    public function lokasi() { return $this->belongsTo(Lokasi::class, 'lokasi_id'); }
    public function details() { return $this->hasMany(ReceivingDetail::class); }
    
    // User Relations
    public function receivedBy() { return $this->belongsTo(User::class, 'received_by'); }
    
    // FIX POIN 2: Tambahkan ini agar tidak error RelationNotFoundException
    public function createdBy() { return $this->belongsTo(User::class, 'created_by')->withDefault(['nama' => 'System']); }
    
    // Tambahan: Relasi untuk QC dan Putaway (karena dipanggil di View Show)
    public function qcBy() { return $this->belongsTo(User::class, 'qc_by')->withDefault(['nama' => '-']); }
    public function putawayBy() { return $this->belongsTo(User::class, 'putaway_by')->withDefault(['nama' => '-']); }

    public function stockMovements() { return $this->morphMany(StockMovement::class, 'referensi'); }

    // Helpers
    public static function generateReceivingNumber()
    {
        $date = now()->format('Ymd');
        $count = self::whereDate('created_at', today())->count() + 1;
        return "RCV/{$date}/" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
    
    // Accessor untuk Badge Status (Poin 3 - Mempercantik)
    public function getStatusBadgeAttribute()
    {
        $badges = [
            'PENDING_QC' => 'Pemeriksaan QC',
            'PENDING_PUTAWAY' => 'Menunggu Penyimpanan',
            'COMPLETED' => 'Selesai',
        ];
        return $badges[$this->status] ?? $this->status;
    }

    public function getStatusClassAttribute()
    {
        $classes = [
            'PENDING_QC' => 'badge-warning',
            'PENDING_PUTAWAY' => 'badge-info',
            'COMPLETED' => 'badge-success',
        ];
        return $classes[$this->status] ?? 'badge-secondary';
    }
}