<?php

namespace App\Services;

use App\Models\Pengaturan;
use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    /**
     * Menstandarkan nomor telepon ke format internasional (628...)
     */
    public function normalizePhone(?string $phone): string
    {
        if (! $phone) {
            return '';
        }

        // Hapus karakter non-angka
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        // Jika diawali '08', ganti '0' dengan '62'
        if (str_starts_with($cleaned, '0')) {
            $cleaned = '62'.substr($cleaned, 1);
        } elseif (str_starts_with($cleaned, '8')) {
            $cleaned = '62'.$cleaned;
        }

        return $cleaned;
    }

    /**
     * Membuat tautan wa.me langsung untuk pengiriman pesan cepat tanpa API pihak ketiga
     */
    public function generateWaMeLink(string $phone, string $message): string
    {
        $normalized = $this->normalizePhone($phone);
        $encoded = urlencode($message);

        return "https://wa.me/{$normalized}?text={$encoded}";
    }

    /**
     * Template pesan pengingat tunggakan santri ke orang tua (Japri)
     */
    public function buildPesanJapriOrtu(string $namaSantri, float $nominalTunggakan, string $bulanTahun): string
    {
        $nominalFormat = number_format($nominalTunggakan, 0, ',', '.');
        $namaInstansi = Pengaturan::getValue('nama_instansi', 'Sekolah / Lembaga');

        return "Assalamu'alaikum Warahmatullahi Wabarakatuh.\n\n"
            ."Yth. Bapak/Ibu Wali dari ananda *{$namaSantri}*,\n\n"
            ."Kami dari *{$namaInstansi}* menginformasikan mengenai kewajiban pembayaran SPP bulan *{$bulanTahun}* sebesar *Rp {$nominalFormat}* yang belum terselesaikan.\n\n"
            ."Mohon agar dapat segera melakukan pembayaran melalui kasir sekolah atau transfer. Apabila Bapak/Ibu telah melakukan pembayaran, mohon abaikan pesan ini dan kirimkan bukti pembayarannya.\n\n"
            ."Jazakumullah khairan katsiran atas perhatian dan kerjasamanya.\n\n"
            ."Wassalamu'alaikum Warahmatullahi Wabarakatuh.";
    }

    /**
     * Template pesan pengingat tunggakan akumulasi (lebih dari 1 bulan)
     */
    public function buildPesanJapriAkumulasi(string $namaSantri, float $totalNominal, int $totalBulan, string $daftarBulanStr): string
    {
        $nominalFormat = number_format($totalNominal, 0, ',', '.');
        $namaInstansi = Pengaturan::getValue('nama_instansi', 'Sekolah / Lembaga');

        return "Assalamu'alaikum Warahmatullahi Wabarakatuh.\n\n"
            ."Yth. Bapak/Ibu Wali dari ananda *{$namaSantri}*,\n\n"
            ."Kami dari *{$namaInstansi}* menginformasikan rekapitulasi kewajiban SPP ananda yang saat ini masih tercatat belum lunas sebanyak *{$totalBulan} bulan* ({$daftarBulanStr}) dengan total tunggakan sebesar *Rp {$nominalFormat}*.\n\n"
            ."Besar harapan kami agar Bapak/Ibu dapat segera menyelesaikan kewajiban administrasi ini demi kelancaran kegiatan belajar mengajar ananda.\n\n"
            ."Jazakumullah khairan katsiran.\n\n"
            ."Wassalamu'alaikum Warahmatullahi Wabarakatuh.";
    }

    /**
     * Template pesan pengumuman grup kelas/jilid
     */
    public function buildPesanGrupJilid(string $namaKelas, float $nominalSpp, string $bulanTahun, string $jatuhTempo = '10'): string
    {
        $nominalFormat = number_format($nominalSpp, 0, ',', '.');
        $namaInstansi = Pengaturan::getValue('nama_instansi', 'Sekolah / Lembaga');

        return "Assalamu'alaikum Warahmatullahi Wabarakatuh.\n\n"
            ."Pengumuman untuk seluruh Wali Santri *{$namaKelas}* - *{$namaInstansi}*:\n\n"
            ."Mengingatkan kembali bahwa masa pembayaran SPP untuk bulan *{$bulanTahun}* (Nominal: *Rp {$nominalFormat}*) telah dibuka. Batas akhir pembayaran jatuh pada tanggal *{$jatuhTempo}*.\n\n"
            ."Pembayaran dapat dilakukan langsung ke bendahara atau melalui transfer.\n\n"
            ."Terima kasih atas perhatian dan dukungannya.\n\n"
            ."Wassalamu'alaikum Warahmatullahi Wabarakatuh.";
    }

    /**
     * Template pesan rekap tunggakan untuk Wali Jilid / Wali Kelas
     */
    public function buildPesanWaliJilid(string $namaKelas, string $waliKelas, int $totalSantri, array $santriMenunggakList, string $bulanTahun): string
    {
        $namaInstansi = Pengaturan::getValue('nama_instansi', 'Sekolah / Lembaga');
        $totalMenunggak = count($santriMenunggakList);
        $totalLunas = max(0, $totalSantri - $totalMenunggak);
        $persentase = ($totalSantri > 0) ? round(($totalLunas / $totalSantri) * 100, 1) : 0;

        $daftarText = '';
        foreach ($santriMenunggakList as $idx => $s) {
            $nom = number_format($s['nominal'], 0, ',', '.');
            $no = $idx + 1;
            $daftarText .= "{$no}. {$s['nama']} (Rp {$nom})\n";
        }

        if (empty($daftarText)) {
            $daftarText = "Alhamdulillah seluruh santri kelas ini telah melunasi SPP.\n";
        }

        return "Assalamu'alaikum Warahmatullahi Wabarakatuh.\n\n"
            ."Laporan Rekapitulasi SPP *{$namaKelas}* - Bulan *{$bulanTahun}*\n"
            ."Wali Jilid: *{$waliKelas}*\n"
            ."Lembaga: *{$namaInstansi}*\n\n"
            ."📊 *Statistik Pembayaran:*\n"
            ."- Total Santri: {$totalSantri}\n"
            ."- Sudah Lunas: {$totalLunas}\n"
            ."- Belum Lunas: {$totalMenunggak}\n"
            ."- Persentase Lunas: {$persentase}%\n\n"
            ."📋 *Daftar Santri Belum Lunas:*\n"
            .$daftarText."\n"
            .'Mohon bantuan ustadz/ustadzah untuk dapat mengingatkan wali santri yang bersangkutan. Jazakumullah khairan.';
    }

    /**
     * Mengirim pesan via Fonnte WhatsApp Gateway API
     */
    public function sendViaFonnte(string $phone, string $message, ?string $customToken = null): array
    {
        $token = $customToken ?: Pengaturan::getValue('fonnte_token');
        if (empty($token)) {
            return [
                'success' => false,
                'message' => 'Token API Fonnte belum dikonfigurasi di Pengaturan.',
            ];
        }

        $target = $this->normalizePhone($phone);
        if (empty($target)) {
            return [
                'success' => false,
                'message' => 'Nomor tujuan tidak valid.',
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $token,
            ])->timeout(15)->post('https://api.fonnte.com/send', [
                'target' => $target,
                'message' => $message,
            ]);

            $json = $response->json();

            if ($response->successful() && isset($json['status']) && $json['status'] == true) {
                return [
                    'success' => true,
                    'message' => 'Pesan WhatsApp berhasil dikirim melalui Fonnte.',
                    'response' => $json,
                ];
            }

            return [
                'success' => false,
                'message' => $json['reason'] ?? 'Gagal mengirim pesan melalui Fonnte.',
                'response' => $json,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Koneksi ke gateway WhatsApp gagal: '.$e->getMessage(),
            ];
        }
    }
}
