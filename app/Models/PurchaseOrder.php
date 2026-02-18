<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    protected $casts = [
        'tanggal_po' => 'date',
        'approved_at' => 'datetime',
    ];

    // Relations
    public function details() { return $this->hasMany(PurchaseOrderDetail::class); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
    
    // Lokasi Peminta (Dealer yang minta barang)
    public function lokasi() { return $this->belongsTo(Lokasi::class, 'lokasi_id'); }
    
    // Sumber Barang (Gudang Pusat)
    public function sumberLokasi() { return $this->belongsTo(Lokasi::class, 'sumber_lokasi_id'); }
    
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function approvedBy() { return $this->belongsTo(User::class, 'approved_by'); }

    // Fix Error RelationNotFoundException: Alias approvedByHead ke approvedBy
    public function approvedByHead() { return $this->belongsTo(User::class, 'approved_by'); }

    // Helpers
    public function syncStatus()
    {
        $this->load('details');
        
        $totalPesan = $this->details->sum('qty_pesan');
        $totalTerima = $this->details->sum('qty_diterima');

        if ($totalPesan > 0 && $totalTerima >= $totalPesan) {
            $this->update(['status' => 'FULLY_RECEIVED']);
        } elseif ($totalTerima > 0) {
            $this->update(['status' => 'PARTIALLY_RECEIVED']);
        }
    }
}