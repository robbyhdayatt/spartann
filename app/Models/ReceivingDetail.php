<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceivingDetail extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    
    public function receiving() { return $this->belongsTo(Receiving::class); }
    public function barang() { return $this->belongsTo(Barang::class, 'barang_id'); }
}