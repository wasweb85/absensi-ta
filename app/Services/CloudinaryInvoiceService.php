<?php

namespace App\Services;

use App\Models\Pengaturan;
use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CloudinaryInvoiceService
{
    /**
     * Memeriksa apakah kredensial Cloudinary telah terkonfigurasi
     */
    public function isConfigured(): bool
    {
        $url = config('cloudinary.url') ?: Pengaturan::getValue('cloudinary_url');
        if (! empty($url)) {
            return true;
        }

        $cloudName = config('cloudinary.cloud_name') ?: Pengaturan::getValue('cloudinary_cloud_name');
        $apiKey = config('cloudinary.api_key') ?: Pengaturan::getValue('cloudinary_api_key');
        $apiSecret = config('cloudinary.api_secret') ?: Pengaturan::getValue('cloudinary_api_secret');

        return ! empty($cloudName) && ! empty($apiKey) && ! empty($apiSecret);
    }

    /**
     * Inisialisasi client Cloudinary resmi
     */
    public function getClient(): ?Cloudinary
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $url = config('cloudinary.url') ?: Pengaturan::getValue('cloudinary_url');
            if (! empty($url)) {
                return new Cloudinary($url);
            }

            $cloudName = config('cloudinary.cloud_name') ?: Pengaturan::getValue('cloudinary_cloud_name');
            $apiKey = config('cloudinary.api_key') ?: Pengaturan::getValue('cloudinary_api_key');
            $apiSecret = config('cloudinary.api_secret') ?: Pengaturan::getValue('cloudinary_api_secret');
            $isSecure = (bool) (config('cloudinary.secure') ?? true);

            $config = Configuration::instance([
                'cloud' => [
                    'cloud_name' => $cloudName,
                    'api_key' => $apiKey,
                    'api_secret' => $apiSecret,
                ],
                'url' => [
                    'secure' => $isSecure,
                ],
            ]);

            return new Cloudinary($config);
        } catch (Throwable $e) {
            Log::error('Gagal menginisialisasi Cloudinary Client: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Mengunggah gambar invoice kuitansi ke Cloudinary
     *
     * @param  string|UploadedFile  $image  Dapat berupa Data URI base64, raw base64, file path, atau UploadedFile
     * @param  string  $nomorKuitansi  Nomor kuitansi transaksi
     * @param  string|null  $invoiceToken  Token unik invoice untuk enkripsi/hashing public_id
     * @return array{success: bool, public_id?: string, secure_url?: string, error?: string}
     */
    public function uploadInvoiceImage(string|UploadedFile $image, string $nomorKuitansi, ?string $invoiceToken = null): array
    {
        $client = $this->getClient();
        if (! $client) {
            return [
                'success' => false,
                'error' => 'Kredensial Cloudinary belum dikonfigurasi di file environment (.env) atau Pengaturan.',
            ];
        }

        // Siapkan payload file / data URI
        $uploadSource = $image;
        if ($image instanceof UploadedFile) {
            $uploadSource = $image->getRealPath();
        } elseif (is_string($image)) {
            $trimmed = trim($image);
            if (str_starts_with($trimmed, 'data:image')) {
                $uploadSource = $trimmed;
            } elseif (base64_decode($trimmed, true) !== false && ! file_exists($trimmed)) {
                $uploadSource = 'data:image/png;base64,'.$trimmed;
            }
        }

        // Keamanan: Generate Public ID acak/pseudonim agar ID santri dan nomor kuitansi tidak terbuka di URL publik CDN
        $salt = $invoiceToken ?: Str::random(16);
        $hashedId = 'inv_'.substr(hash('sha256', $nomorKuitansi.$salt), 0, 24);
        $folder = config('cloudinary.folder', 'tpq_invoices');

        try {
            $uploadResult = $client->uploadApi()->upload($uploadSource, [
                'folder' => $folder,
                'public_id' => $hashedId,
                'resource_type' => 'image',
                'overwrite' => true,
                'format' => 'png',
                'tags' => ['tpq_spp', 'invoice', date('Y-m')],
                'context' => [
                    'nomor_kuitansi' => $nomorKuitansi,
                ],
            ]);

            $secureUrl = $uploadResult['secure_url'] ?? ($uploadResult['url'] ?? null);
            $publicId = $uploadResult['public_id'] ?? null;

            if (empty($secureUrl)) {
                return [
                    'success' => false,
                    'error' => 'Gagal mendapatkan secure URL dari respon Cloudinary.',
                ];
            }

            return [
                'success' => true,
                'public_id' => $publicId,
                'secure_url' => $secureUrl,
            ];
        } catch (Throwable $e) {
            Log::error("Gagal mengunggah invoice SPP {$nomorKuitansi} ke Cloudinary: ".$e->getMessage());

            return [
                'success' => false,
                'error' => 'Terjadi kesalahan saat mengunggah ke Cloudinary: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Menguji koneksi kredensial ke server Cloudinary via Admin API Ping
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(?string $cloudName = null, ?string $apiKey = null, ?string $apiSecret = null, ?string $url = null): array
    {
        try {
            $client = null;
            if (! empty($url)) {
                $client = new Cloudinary($url);
            } elseif (! empty($cloudName) && ! empty($apiKey) && ! empty($apiSecret)) {
                $config = Configuration::instance([
                    'cloud' => [
                        'cloud_name' => $cloudName,
                        'api_key' => $apiKey,
                        'api_secret' => $apiSecret,
                    ],
                    'url' => [
                        'secure' => true,
                    ],
                ]);
                $client = new Cloudinary($config);
            } else {
                $client = $this->getClient();
            }

            if (! $client) {
                return [
                    'success' => false,
                    'message' => 'Kredensial Cloudinary belum diisi lengkap.',
                ];
            }

            $pingResult = $client->adminApi()->ping();
            $status = $pingResult['status'] ?? '';

            if ($status === 'ok') {
                return [
                    'success' => true,
                    'message' => 'Koneksi ke Cloudinary API berhasil terverifikasi! Akun aktif dan siap digunakan.',
                ];
            }

            return [
                'success' => false,
                'message' => 'Respon ping dari Cloudinary tidak sesuai harapan: '.json_encode($pingResult),
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'Gagal terhubung ke Cloudinary: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Menghapus aset gambar invoice dari Cloudinary jika transaksi dibatalkan
     */
    public function deleteInvoiceImage(string $publicId): bool
    {
        $client = $this->getClient();
        if (! $client || empty($publicId)) {
            return false;
        }

        try {
            $result = $client->uploadApi()->destroy($publicId, [
                'resource_type' => 'image',
                'invalidate' => true,
            ]);

            return ($result['result'] ?? '') === 'ok';
        } catch (Throwable $e) {
            Log::warning("Gagal menghapus aset Cloudinary {$publicId}: ".$e->getMessage());

            return false;
        }
    }
}
