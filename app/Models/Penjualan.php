<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Penjualan extends Model
{
    use HasFactory;

    protected $table = 'penjualans';

    // Kita gunakan $fillable untuk keamanan dan memastikan kolom baru terdaftar
    protected $fillable = [
        'nomor_faktur',
        'konsumen_id',
        'lokasi_id',
        'sales_id',
        'created_by',        // Kolom Baru
        'tanggal_jual',
        'total_harga',
        'subtotal',          // Kolom Baru
        'diskon',            // Kolom Baru
        'total_diskon',      // Kolom Legacy (Disamakan dengan diskon)
        'keterangan_diskon', // Kolom Baru
        'pajak',             // Kolom Baru
        'status',            // Kolom Baru
    ];

    protected $casts = [
        'tanggal_jual' => 'date',
    ];

    // --- RELASI ---

    public function details()
    {
        return $this->hasMany(PenjualanDetail::class, 'penjualan_id');
    }

    public function konsumen()
    {
        return $this->belongsTo(Konsumen::class, 'konsumen_id');
    }

    public function lokasi()
    {
        return $this->belongsTo(Lokasi::class, 'lokasi_id');
    }

    public function sales()
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    // Relasi ke User pembuat transaksi (Kasir/PC)
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Relasi Polymorphic ke StockMovement (jika diperlukan untuk tracking)
    public function stockMovements()
    {
        return $this->morphMany(StockMovement::class, 'referensi');
    }

    // Helper Generate Nomor (Opsional, tapi sudah ditangani di Controller)
    public static function generateNomorFaktur()
    {
        $prefix = 'INV/' . date('Ym') . '/';
        $lastSale = DB::table('penjualans')
                        ->where('nomor_faktur', 'like', $prefix . '%')
                        ->orderBy('nomor_faktur', 'desc')
                        ->first();

        $newNumber = $lastSale ? ((int) substr($lastSale->nomor_faktur, -4)) + 1 : 1;
        return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }
}