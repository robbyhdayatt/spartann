<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'nik', 'nama', 'username', 'email', 'password', 'lokasi_id', 'jabatan_id', 'is_active',
    ];

    protected $hidden = [ 'password', 'remember_token', ];

    protected $casts = [ 'email_verified_at' => 'datetime', ];

    public function jabatan()
    {
        return $this->belongsTo(Jabatan::class);
    }

    public function lokasi()
    {
        return $this->belongsTo(Lokasi::class, 'lokasi_id');
    }

    public function adminlte_desc()
    {
        $loc = $this->lokasi ? " - " . $this->lokasi->singkatan : "";
        return ($this->jabatan->nama_jabatan ?? 'N/A') . $loc;
    }

    public function hasRole($roles)
    {
        if (!$this->jabatan || !$this->jabatan->is_active) {
            return false;
        }

        if (is_string($roles)) {
            return $this->jabatan->singkatan === $roles;
        }
        return in_array($this->jabatan->singkatan, $roles);
    }

    // --- NEW CORE HELPERS ---

    public function isGlobal()
    {
        return $this->hasRole(['SA', 'PIC']);
    }

    public function isPusat()
    {
        return $this->lokasi && $this->lokasi->tipe === 'PUSAT';
    }

    public function isGudang()
    {
        return $this->lokasi && $this->lokasi->tipe === 'GUDANG';
    }

    public function isDealer()
    {
        return $this->lokasi && $this->lokasi->tipe === 'DEALER';
    }
}