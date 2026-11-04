<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ActivityLog extends Model
{
    use HasFactory;

    protected $table = 'activity_logs';

    protected static bool $schemaChecked = false;

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
     * Memastikan skema tabel activity_logs tersedia secara otomatis
     * (Self-healing untuk database cloud TiDB / serverless Vercel jika migrasi belum dijalankan).
     */
    public static function ensureSchemaExists(): void
    {
        if (self::$schemaChecked) {
            return;
        }
        self::$schemaChecked = true;

        try {
            if (! Schema::hasTable('activity_logs')) {
                Schema::create('activity_logs', function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('id_user')->nullable()->index();
                    $table->string('nama_user', 100);
                    $table->string('role', 30)->nullable();
                    $table->string('modul', 50)->index();
                    $table->string('aksi', 50)->index();
                    $table->text('deskripsi');
                    $table->string('ip_address', 45)->nullable();
                    $table->text('user_agent')->nullable();
                    $table->json('properties')->nullable();
                    $table->timestamps();

                    $table->index('created_at');
                    $table->index(['modul', 'created_at']);
                });

                try {
                    Schema::table('activity_logs', function (Blueprint $table) {
                        $table->foreign('id_user')->references('id')->on('users')->nullOnDelete();
                    });
                } catch (\Throwable) {
                    // Abaikan jika FK tidak didukung atau terkendala di lingkungan terdistribusi
                }

                // Catat log audit perdana
                try {
                    self::create([
                        'id_user' => null,
                        'nama_user' => 'Sistem TPQ',
                        'role' => 'system',
                        'modul' => 'pengaturan',
                        'aksi' => 'inisialisasi',
                        'deskripsi' => 'Modul audit trail log aktivitas sistem berhasil diinisialisasi otomatis.',
                        'ip_address' => '127.0.0.1',
                        'user_agent' => 'System/AutoSchema',
                        'properties' => ['status' => 'initialized'],
                    ]);
                } catch (\Throwable) {
                    // Abaikan jika pencatatan perdana gagal
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Gagal auto-create schema activity_logs: '.$e->getMessage());
        }
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
            self::ensureSchemaExists();

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
