<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $table = 'services'; // Menyesuaikan dengan nama tabel baru
    protected $guarded = ['id'];

    public function details()
    {
        return $this->hasMany(ServiceDetail::class);
    }
}
