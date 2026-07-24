<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Konsumen extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'kode_konsumen', 
        'nama_konsumen', 
        'tipe_konsumen', 
        'alamat', 
        'telepon', 
        'is_active'
    ];
}