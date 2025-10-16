<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceDetail extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * Mendefinisikan relasi ke model Service (induknya).
     */
    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Mendefinisikan relasi ke model Part.
     * Ini menghubungkan 'item_code' di tabel ini dengan 'kode_part' di tabel parts.
     */
    public function part()
    {
        // Foreign key di tabel ini adalah 'item_code'
        // Owner key (primary key di tabel parts) adalah 'kode_part'
        return $this->belongsTo(Part::class, 'item_code', 'kode_part');
    }
}
