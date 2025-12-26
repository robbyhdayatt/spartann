<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturnDetail extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * Relasi ke Barang (Pengganti Part)
     * Pastikan kolom di database purchase_return_details adalah 'barang_id'.
     * Jika masih 'part_id', ubah parameter kedua menjadi 'part_id'.
     */
    public function barang()
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    /**
     * Deprecated: Relasi lama (bisa dihapus jika sudah tidak dipakai)
     * Saya arahkan ke Barang juga untuk safety.
     */
    public function part()
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function purchaseReturn()
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    public function receivingDetail()
    {
        return $this->belongsTo(ReceivingDetail::class, 'receiving_detail_id');
    }
}