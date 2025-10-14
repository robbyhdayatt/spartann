<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lokasi extends Model
{
    use HasFactory;

    protected $table = 'lokasi';
    protected $guarded = ['id'];

    public function raks()
    {
        return $this->hasMany(Rak::class, 'gudang_id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'gudang_id');
    }
}
