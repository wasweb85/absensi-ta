<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

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

    /**
     * Guru / Ustadz yang ditugaskan sebagai wali jilid ini
     */
    public function guru(): HasOne
    {
        return $this->hasOne(User::class, 'id_kelas')
            ->whereIn('role', ['guru', 'sekretaris', 'bendahara']);
    }

    /**
     * Seluruh ustadz/ustadzah berperan guru yang menjadi wali jilid ini (bisa putra, putri, atau campur)
     */
    public function waliGuru(): HasMany
    {
        return $this->hasMany(User::class, 'id_kelas')->where('role', 'guru');
    }

    /**
     * Menyusun label wali jilid dari daftar guru berdasarkan kelompok binaan.
     *
     * @param  Collection<int, User>  $teachers
     */
    public static function buildWaliLabel(Collection $teachers): ?string
    {
        if ($teachers->isEmpty()) {
            return null;
        }

        $waliCampur = $teachers->first(fn (User $u) => in_array($u->gender_binaan, ['all', null, ''], true));
        if ($waliCampur) {
            return $waliCampur->name;
        }

        $putra = $teachers->firstWhere('gender_binaan', 'L');
        $putri = $teachers->firstWhere('gender_binaan', 'P');

        return match (true) {
            $putra && $putri => "{$putra->name} (Putra) & {$putri->name} (Putri)",
            (bool) $putra => "{$putra->name} (Putra)",
            (bool) $putri => "{$putri->name} (Putri)",
            default => null,
        };
    }
}
