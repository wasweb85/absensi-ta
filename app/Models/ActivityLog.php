<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class ActivityLog extends Model
{
    use HasFactory;

    protected $table = 'activity_logs';

    protected $fillable = [
        'id_user',
        'nama_user',
        'role',
        'modul',
        'aksi',
        'deskripsi',
        'ip_address',
        'user_agent',
        'properties',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    /**
     * Catat log aktivitas penting ke database secara aman tanpa memicu crash jika terjadi kegagalan
     */
    public static function record(
        string $modul,
        string $aksi,
        string $deskripsi,
        ?array $properties = null,
        ?User $user = null
    ): ?self {
        try {
            $currentUser = $user ?? auth('sanctum')->user() ?? auth()->user();

            $ipAddress = null;
            $userAgent = null;
            if (app()->bound('request')) {
                $req = request();
                $ipAddress = $req->ip();
                $userAgent = $req->userAgent();
            }

            return self::create([
                'id_user' => $currentUser?->id,
                'nama_user' => $currentUser?->name ?: 'Sistem',
                'role' => $currentUser?->role ?: 'system',
                'modul' => $modul,
                'aksi' => $aksi,
                'deskripsi' => $deskripsi,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent ? substr($userAgent, 0, 500) : null,
                'properties' => $properties,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat ActivityLog: '.$e->getMessage(), [
                'modul' => $modul,
                'aksi' => $aksi,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
