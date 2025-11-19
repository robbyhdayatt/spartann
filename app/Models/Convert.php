<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Convert extends Model
{
    use HasFactory;

    protected $table = 'converts';

    protected $guarded = ['id'];

    protected $casts = [
        'selling_out' => 'decimal:2',
        'retail' => 'decimal:2',
    ];
}
