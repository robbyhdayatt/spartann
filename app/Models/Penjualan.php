<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Penjualan extends Model
{
    use HasFactory;

    protected $table = 'penjualans';

    protected $fillable = [
        'nomor_faktur',
        'konsumen_id',
        'lokasi_id',
        'sales_id',
        'created_by',
        'total_harga',
        'subtotal',         
        'diskon',           
        'total_diskon',
        'keterangan_diskon',
        'pajak',            
        'status',           
        'tanggal_jual',
    ];

    protected $casts = [
        'tanggal_jual' => 'date',
        'total_harga' => 'float',
        'subtotal' => 'float',
        'pajak' => 'float',
        'diskon' => 'float',
    ];

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

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stockMovements()
    {
        return $this->morphMany(StockMovement::class, 'referensi');
    }

    public static function generateNomorFaktur($lokasiId = null)
    {
        // 1. Tentukan Kode Dealer (Default 'PST' jika tidak ada lokasi)
        $kodeDealer = 'PST'; 
        
        if ($lokasiId) {
            $lokasi = \App\Models\Lokasi::find($lokasiId);
            // Asumsi kolom kode di tabel lokasi bernama 'kode_lokasi'
            if ($lokasi && $lokasi->kode_lokasi) {
                $kodeDealer = $lokasi->kode_lokasi;
            }
        }

        // 2. Format Tanggal ymd (Contoh: 251231 untuk 31 Des 2025)
        $tanggalMurni = now()->format('ymd');

        // 3. Susun Prefix (Contoh: INV/UA22001/251231/)
        $prefix = "INV/{$kodeDealer}/{$tanggalMurni}/";
        
        // 4. Cari faktur terakhir di cabang tersebut pada hari ini
        $lastPenjualan = self::where('nomor_faktur', 'LIKE', $prefix . '%')
            ->orderBy('nomor_faktur', 'desc')
            ->first();

        // 5. Penomoran Otomatis (Reset ke 1 tiap hari baru)
        if ($lastPenjualan) {
            // Ambil 4 digit terakhir dan tambah 1
            $lastNumber = (int) substr($lastPenjualan->nomor_faktur, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }
}