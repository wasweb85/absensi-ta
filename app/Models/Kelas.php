<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Kelas extends Model
{
    use HasFactory;

    protected $table = 'kelas';

    protected $fillable = [
        'nama_kelas',
        'wali_kelas',
        'no_hp_wali',
        'warna_label',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
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

    /**
     * Guru / Ustadz yang ditugaskan sebagai wali jilid ini
     */
    public function guru(): HasOne
    {
        return $this->hasOne(User::class, 'id_kelas')
            ->whereIn('role', ['guru', 'sekretaris', 'bendahara']);
    }
}
