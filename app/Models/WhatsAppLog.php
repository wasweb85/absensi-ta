<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppLog extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_logs';

    protected $fillable = [
        'fonnte_id',
        'nomor_tujuan',
        'nama_penerima',
        'pesan',
        'tipe_pesan',
        'status',
        'keterangan_error',
        'retry_count',
        'last_retried_at',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'retry_count' => 'integer',
            'last_retried_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    /**
     * Tandai pesan telah diterima oleh gateway / server WhatsApp (Centang 1)
     */
    public function markAsSent(?string $fonnteId = null): void
    {
        $updates = [
            'status' => 'sent',
            'sent_at' => Carbon::now('Asia/Jakarta'),
            'keterangan_error' => null,
        ];

        if ($fonnteId) {
            $updates['fonnte_id'] = $fonnteId;
        }

        $this->update($updates);
    }

    /**
     * Tandai pesan telah tersampaikan ke perangkat penerima (Centang 2 Abu)
     */
    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => Carbon::now('Asia/Jakarta'),
            'sent_at' => $this->sent_at ?: Carbon::now('Asia/Jakarta'),
        ]);
    }

    /**
     * Tandai pesan telah dibuka & dibaca oleh penerima (Centang 2 Biru)
     */
    public function markAsRead(): void
    {
        $now = Carbon::now('Asia/Jakarta');
        $this->update([
            'status' => 'read',
            'read_at' => $now,
            'delivered_at' => $this->delivered_at ?: $now,
            'sent_at' => $this->sent_at ?: $now,
        ]);
    }

    /**
     * Tandai pesan gagal terkirim
     */
    public function markAsFailed(?string $reason = null): void
    {
        $this->update([
            'status' => 'failed',
            'keterangan_error' => $reason ?: 'Gagal diproses oleh WhatsApp Gateway.',
        ]);
    }

    /**
     * Catat proses kirim ulang pesan
     */
    public function recordRetry(): void
    {
        $this->increment('retry_count', 1, [
            'status' => 'pending',
            'last_retried_at' => Carbon::now('Asia/Jakarta'),
            'keterangan_error' => null,
        ]);
    }

    /**
     * Scope pencarian kata kunci pada nomor, nama, dan isi pesan
     */
    public function scopeSearch(Builder $query, ?string $keyword): Builder
    {
        if (empty($keyword)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($keyword) {
            $q->where('nomor_tujuan', 'like', "%{$keyword}%")
                ->orWhere('nama_penerima', 'like', "%{$keyword}%")
                ->orWhere('pesan', 'like', "%{$keyword}%");
        });
    }

    /**
     * Scope filter berdasarkan status
     */
    public function scopeFilterStatus(Builder $query, ?string $status): Builder
    {
        if (empty($status) || $status === 'all') {
            return $query;
        }

        return $query->where('status', $status);
    }

    /**
     * Scope filter berdasarkan tipe pesan
     */
    public function scopeFilterTipe(Builder $query, ?string $tipe): Builder
    {
        if (empty($tipe) || $tipe === 'all') {
            return $query;
        }

        return $query->where('tipe_pesan', $tipe);
    }
}
