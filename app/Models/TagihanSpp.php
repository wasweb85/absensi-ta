<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TagihanSpp extends Model
{
    use HasFactory;

    protected $table = 'tagihan_spp';

    protected $fillable = [
        'id_siswa',
        'bulan',
        'tahun',
        'tahun_ajaran',
        'nominal_tagihan',
        'status',
        'jatuh_tempo',
    ];

    protected function casts(): array
    {
        return [
            'nominal_tagihan' => 'decimal:2',
            'bulan' => 'integer',
            'tahun' => 'integer',
            'jatuh_tempo' => 'date:Y-m-d',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'id_siswa');
    }

    public function pembayaran(): HasMany
    {
        return $this->hasMany(PembayaranSpp::class, 'id_tagihan');
    }
}
