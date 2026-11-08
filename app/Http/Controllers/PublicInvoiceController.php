<?php

namespace App\Http\Controllers;

use App\Models\PembayaranSpp;
use App\Models\Pengaturan;
use App\Models\User;
use App\Services\SppBillingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PublicInvoiceController extends Controller
{
    /**
     * Menampilkan laman bukti pembayaran resmi SPP berbasis token acak yang aman
     */
    public function show(string $token, Request $request): View|RedirectResponse
    {
        // Keamanan: Validasi format token (hanya string alfanumerik 20-64 karakter)
        if (! preg_match('/^[a-fA-F0-9]{32,64}$/', $token)) {
            abort(404, 'Tautan bukti pembayaran tidak valid atau format salah.');
        }

        $pembayaran = PembayaranSpp::with(['siswa.kelas', 'tagihan', 'petugas'])
            ->where('invoice_token', $token)
            ->first();

        if (! $pembayaran) {
            abort(404, 'Tanda bukti pembayaran tidak ditemukan atau tautan telah kedaluwarsa.');
        }

        $siswa = $pembayaran->siswa;
        $tagihan = $pembayaran->tagihan;

        // Rangkum informasi periode bulan
        $namaBulan = SppBillingService::NAMA_BULAN[$tagihan?->bulan] ?? ($tagihan ? "Bulan {$tagihan->bulan}" : '-');
        $periodeStr = $tagihan ? "{$namaBulan} {$tagihan->tahun}" : '-';

        // Deteksi jika pembayaran adalah bagian dari multi-bulan
        $multiPayments = collect([$pembayaran]);
        $totalNominal = (float) $pembayaran->jumlah_bayar;

        if (str_contains($pembayaran->nomor_kuitansi, '-')) {
            $parts = explode('-', $pembayaran->nomor_kuitansi);
            if (count($parts) >= 3) {
                $basePrefix = $parts[0].'-'.$parts[1];
                $related = PembayaranSpp::with('tagihan')
                    ->where('nomor_kuitansi', 'like', "{$basePrefix}%")
                    ->where('id_siswa', $pembayaran->id_siswa)
                    ->orderBy('nomor_kuitansi', 'asc')
                    ->get();

                if ($related->isNotEmpty()) {
                    $multiPayments = $related;
                    $totalNominal = (float) $related->sum('jumlah_bayar');

                    $daftarBulanList = [];
                    foreach ($related as $rel) {
                        $blnName = SppBillingService::NAMA_BULAN[$rel->tagihan?->bulan] ?? "Bln {$rel->tagihan?->bulan}";
                        $daftarBulanList[] = "{$blnName} {$rel->tagihan?->tahun}";
                    }
                    if (! empty($daftarBulanList)) {
                        $periodeStr = implode(', ', $daftarBulanList);
                    }
                }
            }
        }

        // Keamanan privasi: Samarkan nomor HP orang tua (Masking PII)
        $maskedPhone = $this->maskPhoneNumber($siswa?->no_hp_ortu);

        // Ambil nama bendahara & instansi
        $namaBendahara = User::getNamaBendahara();
        $namaInstansi = Pengaturan::getValue('nama_sekolah', "TPQ Nurul Mun'im");
        $kontakBendahara = Pengaturan::getValue('no_hp_bendahara', '085235567818');

        return view('public_invoice', [
            'pembayaran' => $pembayaran,
            'siswa' => $siswa,
            'tagihan' => $tagihan,
            'periodeStr' => $periodeStr,
            'totalNominal' => $totalNominal,
            'multiPayments' => $multiPayments,
            'maskedPhone' => $maskedPhone,
            'namaBendahara' => $namaBendahara,
            'namaInstansi' => $namaInstansi,
            'kontakBendahara' => $kontakBendahara,
            'isValid' => $pembayaran->status_transaksi === 'valid',
            'cloudinaryUrl' => $pembayaran->cloudinary_url,
        ]);
    }

    /**
     * Sensor nomor telepon orang tua untuk menjaga privasi publik (misal: 0852****7818)
     */
    protected function maskPhoneNumber(?string $phone): string
    {
        if (empty($phone)) {
            return '-';
        }

        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        $len = strlen($cleaned);

        if ($len <= 6) {
            return str_repeat('*', $len);
        }

        $start = substr($cleaned, 0, 4);
        $end = substr($cleaned, -3);
        $maskedMiddle = str_repeat('*', max(3, $len - 7));

        return "{$start}{$maskedMiddle}{$end}";
    }
}
