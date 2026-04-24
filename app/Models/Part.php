<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Part extends Model
{
    use HasFactory;

    protected $table = 'parts';

    protected $fillable = [
        'kode_part',
        'nama_part',
        'stok_minimum',
        'qty_stok',
        'retail',
        'is_active',
    ];
}