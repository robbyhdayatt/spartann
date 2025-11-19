<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\PurchaseOrderDetail; // (Opsional jika namespace sama)
// use App\Models\Supplier;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'tanggal_po' => 'date',
        'approved_at' => 'datetime',
    ];

    public function details()
    {
        return $this->hasMany(PurchaseOrderDetail::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    // --- TAMBAHKAN RELASI INI ---
    public function sumberLokasi()
    {
        return $this->belongsTo(Lokasi::class, 'sumber_lokasi_id');
    }
    // ----------------------------

    public function lokasi()
    {
        return $this->belongsTo(Lokasi::class, 'lokasi_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ... (method getStatusClassAttribute dll tetap sama)
     public function getStatusClassAttribute()
    {
        switch ($this->status) {
            case 'PENDING_APPROVAL': return 'badge-warning';
            case 'APPROVED': return 'badge-success';
            case 'REJECTED': return 'badge-danger';
            case 'PARTIALLY_RECEIVED': return 'badge-info';
            case 'FULLY_RECEIVED': return 'badge-primary';
            default: return 'badge-secondary';
        }
    }

    public function getStatusBadgeAttribute()
    {
        return str_replace('_', ' ', $this->status);
    }
}
