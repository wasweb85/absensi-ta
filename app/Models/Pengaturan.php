<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Pengaturan extends Model
{
    protected $table = 'pengaturan';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        $val = Cache::remember("pengaturan_{$key}", 86400, function () use ($key, $default) {
            $setting = static::find($key);

            return $setting ? $setting->value : $default;
        });

        if ($key === 'nama_instansi') {
            if (empty($val) || in_array(strtoupper(trim($val)), ['E ABSENSI', 'E-ABSENSI', 'SISTEM TERPADU ABSENSI SANTRI', 'SISTEM TERPADU PRESENSI & NOTIFIKASI WHATSAPP SANTRI', 'TPQ & LEMBAGA'])) {
                return "TPQ Nurul Mun'im";
            }
        }

        return $val;
    }

    public static function setValue(string $key, ?string $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        Cache::forget("pengaturan_{$key}");
    }
}
