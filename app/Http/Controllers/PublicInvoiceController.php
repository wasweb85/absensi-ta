<?php

namespace App\Http\Controllers;

use App\Models\PembayaranSpp;
use App\Models\Pengaturan;
use App\Models\User;
use App\Services\SppBillingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PublicInvoiceController extends Controller
{
    /**
     * Menampilkan laman bukti pembayaran resmi SPP berbasis token acak yang aman
     */
    public function show(string $token, Request $request): View|RedirectResponse
    {
        SppBillingService::ensureSppSchemaExists();

        $token = trim($token);
        $pembayaran = null;

        // 1. Cek jika parameter adalah invoice_token acak (hex 32-64 karakter)
        if (preg_match('/^[a-fA-F0-9]{32,64}$/', $token)) {
            try {
                if (Schema::hasColumn('pembayaran_spp', 'invoice_token')) {
                    $pembayaran = PembayaranSpp::with(['siswa.kelas', 'tagihan', 'petugas'])
                        ->where('invoice_token', $token)
                        ->first();
                }
            } catch (\Throwable) {
            }

            // Fallback deterministic SHA256 matching jika kolom belum termigrasi di database remote
            if (! $pembayaran) {
                $candidates = PembayaranSpp::with(['siswa.kelas', 'tagihan', 'petugas'])
                    ->orderByDesc('id')
                    ->limit(200)
                    ->get();

                foreach ($candidates as $cand) {
                    $expectedToken = substr(hash('sha256', ($cand->nomor_kuitansi ?: ('KW-'.$cand->id)).(config('app.key') ?: 'tpq_secret')), 0, 48);
                    if (hash_equals($expectedToken, $token)) {
                        $pembayaran = $cand;
                        break;
                    }
                }
            }
        } elseif (preg_match('/^KW-[0-9A-Za-z\-_]+$/i', $token)) {
            // 2. Dukungan akses via nomor kuitansi resmi (misal: KW-202611-00031)
            $pembayaran = PembayaranSpp::with(['siswa.kelas', 'tagihan', 'petugas'])
                ->where('nomor_kuitansi', 'like', $token.'%')
                ->first();

            // Jika pembayaran sudah memiliki invoice_token, alihkan ke token yang aman
            if ($pembayaran && ! empty($pembayaran->invoice_token)) {
                return redirect()->route('public.invoice.show', ['token' => $pembayaran->invoice_token]);
            }
        } else {
            abort(404, 'Tautan bukti pembayaran tidak valid atau format salah.');
        }

        if (! $pembayaran) {
            abort(404, 'Tanda bukti pembayaran tidak ditemukan atau tautan telah kedaluwarsa.');
        }

        $siswa = $pembayaran->siswa;
        $tagihan = $pembayaran->tagihan;

        // Rangkum informasi periode bulan transaksi utama
        $namaBulan = SppBillingService::NAMA_BULAN[$tagihan?->bulan] ?? ($tagihan ? "Bulan {$tagihan->bulan}" : '-');
        $periodeStr = $tagihan ? "{$namaBulan} {$tagihan->tahun}" : '-';

        // Deteksi apakah pembayaran ini adalah bagian dari transaksi multi-bulan
        // Format nomor kuitansi standar: KW-YYYYMM-XXXXX (3 bagian: KW, YYYYMM, XXXXX)
        // Format nomor kuitansi multi-bulan: KW-YYYYMM-XXXXX-1, KW-YYYYMM-XXXXX-2 (>= 4 bagian)
        $multiPayments = collect([$pembayaran]);
        $totalNominal = (float) $pembayaran->jumlah_bayar;

        $parts = explode('-', $pembayaran->nomor_kuitansi);
        $baseKuitansi = null;

        if (count($parts) >= 4) {
            // Ini adalah salah satu item dari transaksi multi-bulan (misal: KW-202611-00096-1)
            $baseKuitansi = $parts[0].'-'.$parts[1].'-'.$parts[2];
        } else {
            // Cek apakah nomor kuitansi ini adalah nomor induk yang memiliki pecahan -1, -2, dst.
            $hasSubItems = PembayaranSpp::where('nomor_kuitansi', 'like', "{$pembayaran->nomor_kuitansi}-%")
                ->where('id_siswa', $pembayaran->id_siswa)
                ->where('status_transaksi', 'valid')
                ->exists();

            if ($hasSubItems) {
                $baseKuitansi = $pembayaran->nomor_kuitansi;
            }
        }

        // HANYA gabungkan transaksi jika terbukti merupakan transaksi multi-bulan yang sama
        if ($baseKuitansi !== null) {
            $related = PembayaranSpp::with('tagihan')
                ->where('id_siswa', $pembayaran->id_siswa)
                ->where('status_transaksi', 'valid')
                ->where(function ($q) use ($baseKuitansi) {
                    $q->where('nomor_kuitansi', $baseKuitansi)
                        ->orWhere('nomor_kuitansi', 'like', "{$baseKuitansi}-%");
                })
                ->orderBy('id', 'asc')
                ->get();

            // Urutkan secara kronologis berdasarkan kalender (tahun & bulan)
            $related = $related->sortBy(function ($item) {
                if (! $item->tagihan) {
                    return $item->id;
                }

                return ($item->tagihan->tahun * 100) + (int) $item->tagihan->bulan;
            })->values();

            if ($related->isNotEmpty()) {
                $multiPayments = $related;
                $totalNominal = (float) $related->sum('jumlah_bayar');

                $daftarBulanList = [];
                foreach ($related as $rel) {
                    if ($rel->tagihan) {
                        $blnName = SppBillingService::NAMA_BULAN[$rel->tagihan->bulan] ?? "Bln {$rel->tagihan->bulan}";
                        $itemPeriode = "{$blnName} {$rel->tagihan->tahun}";
                        if (! in_array($itemPeriode, $daftarBulanList, true)) {
                            $daftarBulanList[] = $itemPeriode;
                        }
                    }
                }

                if (! empty($daftarBulanList)) {
                    $periodeStr = implode(', ', $daftarBulanList);
                }
            }
        }

        // Keamanan privasi: Samarkan nomor HP orang tua (Masking PII)
        $maskedPhone = $this->maskPhoneNumber($siswa?->no_hp_ortu);

        // Ambil nama bendahara & instansi
        $namaBendahara = User::getNamaBendahara();
        $namaInstansi = Pengaturan::getValue('nama_sekolah', "TPQ Nurul Mun'im");
        $kontakBendahara = Pengaturan::getValue('no_hp_bendahara', '085235567818');

        $nomorKuitansiDisplay = $baseKuitansi ?: $pembayaran->nomor_kuitansi;

        return view('public_invoice', [
            'pembayaran' => $pembayaran,
            'nomorKuitansiDisplay' => $nomorKuitansiDisplay,
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
