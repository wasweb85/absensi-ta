<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Siswa extends Model
{
    use HasFactory;

    protected $table = 'siswa';

    protected $fillable = [
        'nisn',
        'nama_lengkap',
        'jenis_kelamin',
        'id_kelas',
        'no_hp_ortu',
        'nama_ortu',
        'alamat',
        'status',
        'foto',
    ];

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'id_kelas');
    }

    public function absensi(): HasMany
    {
        return $this->hasMany(Absensi::class, 'id_siswa');
    }

    public function tagihanSpp(): HasMany
    {
        return $this->hasMany(TagihanSpp::class, 'id_siswa');
    }

    public function pembayaranSpp(): HasMany
    {
        return $this->hasMany(PembayaranSpp::class, 'id_siswa');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'nisn', 'nisn');
    }
}
