<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class PembayaranSpp extends Model
{
    use HasFactory;

    protected $table = 'pembayaran_spp';

    protected $fillable = [
        'id_tagihan',
        'id_siswa',
        'nomor_kuitansi',
        'tanggal_bayar',
        'jumlah_bayar',
        'metode_pembayaran',
        'id_petugas',
        'status_transaksi',
        'alasan_pembatalan',
        'dibatalkan_oleh',
        'catatan',
        'cloudinary_public_id',
        'cloudinary_url',
        'invoice_token',
        'upload_status',
    ];

    protected $appends = [
        'secure_invoice_url',
    ];

    protected static function booted(): void
    {
        static::creating(function (PembayaranSpp $pembayaran) {
            if (empty($pembayaran->invoice_token)) {
                $pembayaran->invoice_token = static::generateUniqueToken();
            }
        });
    }

    /**
     * Menghasilkan token acak kriptografis 48-karakter unik untuk akses publik aman
     */
    public static function generateUniqueToken(): string
    {
        return bin2hex(random_bytes(24));
    }

    /**
     * Mengambil URL publik invoice yang aman
     */
    public function getSecureInvoiceUrlAttribute(): string
    {
        $token = $this->invoice_token;
        if (empty($token)) {
            $token = substr(hash('sha256', ($this->nomor_kuitansi ?: 'KW').(config('app.key') ?: 'tpq_secret')), 0, 48);
            if (Schema::hasColumn('pembayaran_spp', 'invoice_token')) {
                try {
                    $this->invoice_token = $token;
                    $this->saveQuietly();
                } catch (\Throwable) {
                    // Abaikan jika database remote belum sempat migrate
                }
            }
        }

        return url('/invoice/'.$token);
    }

    protected function casts(): array
    {
        return [
            'tanggal_bayar' => 'datetime',
            'jumlah_bayar' => 'decimal:2',
        ];
    }

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->setTimezone(new \DateTimeZone('Asia/Jakarta'))->format('Y-m-d H:i:s');
    }

    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(TagihanSpp::class, 'id_tagihan');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'id_siswa');
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_petugas');
    }
}
