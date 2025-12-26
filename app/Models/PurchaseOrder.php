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
        'approved_by_head_at' => 'datetime', // Baru
    ];

    // --- SCOPES ---
    public function scopeDealerRequest($query)
    {
        return $query->where('po_type', 'dealer_request');
    }

    public function scopeSupplierPO($query)
    {
        return $query->where('po_type', 'supplier_po');
    }

    // --- RELATIONS ---
    public function details()
    {
        return $this->hasMany(PurchaseOrderDetail::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lokasi()
    {
        return $this->belongsTo(Lokasi::class, 'lokasi_id');
    }
    
    public function sumberLokasi()
    {
        return $this->belongsTo(Lokasi::class, 'sumber_lokasi_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy() // Admin Gudang / Admin Dealer
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
    
    public function approvedByHead() // Kepala Gudang (Baru)
    {
        return $this->belongsTo(User::class, 'approved_by_head_id');
    }

    public function getStatusClassAttribute()
    {
        switch ($this->status) {
            case 'PENDING_APPROVAL': return 'badge-warning';
            case 'APPROVED': return 'badge-success';
            case 'REJECTED': return 'badge-danger';
            case 'PARTIALLY_RECEIVED': return 'badge-info'; // Badge Biru Muda
            case 'FULLY_RECEIVED': return 'badge-primary'; // Badge Biru Tua
            default: return 'badge-secondary';
        }
    }
    
    public function getStatusBadgeAttribute() {
        return str_replace('_', ' ', $this->status);
    }

    /**
     * --- METHOD BARU: HITUNG ULANG STATUS OTOMATIS ---
     * Panggil method ini setiap kali ada penerimaan barang (Receiving/Putaway)
     */
    public function syncStatus()
    {
        $this->load('details');
        
        $totalPesan = 0;
        $totalDiterima = 0;
        
        foreach ($this->details as $detail) {
            $totalPesan += $detail->qty_pesan;
            // Pastikan kolom qty_diterima di tabel purchase_order_details diupdate saat ReceivingController
            $totalDiterima += $detail->qty_diterima; 
        }

        if ($totalDiterima >= $totalPesan && $totalPesan > 0) {
            $this->update(['status' => 'FULLY_RECEIVED']);
        } elseif ($totalDiterima > 0) {
            $this->update(['status' => 'PARTIALLY_RECEIVED']);
        } else {
            // Jika belum ada yang diterima sama sekali, biarkan status APPROVED
            // Tidak perlu diubah kembali ke pending
        }
    }
}