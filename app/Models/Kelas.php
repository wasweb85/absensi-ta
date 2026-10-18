<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kelas extends Model
{
    use HasFactory;

    protected $table = 'kelas';

    protected $fillable = [
        'nama_kelas',
        'nominal_spp',
        'wali_kelas',
        'no_hp_wali',
        'warna_label',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'nominal_spp' => 'decimal:2',
            'urutan' => 'integer',
        ];
    }

    public function siswa(): HasMany
    {
        return $this->hasMany(Siswa::class, 'id_kelas');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'id_kelas');
    }
}
