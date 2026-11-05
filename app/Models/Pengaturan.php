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

    /**
     * Cache memori runtime per siklus request untuk akses instan tanpa roundtrip SQL berulang
     *
     * @var array<string, ?string>
     */
    protected static array $runtimeCache = [];

    /**
     * Mengambil seluruh pengaturan dalam 1 query terpusat yang di-cache di memori
     *
     * @return array<string, ?string>
     */
    public static function getAllSettings(): array
    {
        if (app()->runningUnitTests()) {
            return static::pluck('value', 'key')->all();
        }

        if (! empty(static::$runtimeCache)) {
            return static::$runtimeCache;
        }

        try {
            $cached = Cache::remember('all_pengaturan_map', 86400, function () {
                return static::pluck('value', 'key')->all();
            });
            static::$runtimeCache = is_array($cached) ? $cached : [];
        } catch (\Throwable) {
            static::$runtimeCache = static::pluck('value', 'key')->all();
        }

        return static::$runtimeCache;
    }

    public static function getValue(string $key, ?string $default = null): ?string
    {
        $all = static::getAllSettings();
        $val = array_key_exists($key, $all) ? $all[$key] : null;

        if ($val === null) {
            $val = $default;
        }

        if ($key === 'nama_instansi') {
            if (empty($val) || in_array(strtoupper(trim((string) $val)), ['E ABSENSI', 'E-ABSENSI', 'SISTEM TERPADU ABSENSI SANTRI', 'SISTEM TERPADU PRESENSI & NOTIFIKASI WHATSAPP SANTRI', 'TPQ & LEMBAGA'])) {
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

        static::$runtimeCache = [];
        Cache::forget('all_pengaturan_map');
        Cache::forget("pengaturan_{$key}");
    }

    /**
     * Menghapus seluruh cache pengaturan di memori dan storage
     */
    public static function clearCache(): void
    {
        static::$runtimeCache = [];
        Cache::forget('all_pengaturan_map');
    }
}
