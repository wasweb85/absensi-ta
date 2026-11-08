<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bukti Pembayaran SPP Resmi - {{ $namaInstansi }}</title>

    <!-- Open Graph / WhatsApp Preview Meta Tags -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $namaInstansi }}">
    <meta property="og:title" content="Bukti Pembayaran SPP Resmi - {{ $namaInstansi }}">
    <meta property="og:description" content="Kuitansi {{ $pembayaran->nomor_kuitansi }} ananda {{ $siswa?->nama_lengkap ?? 'Santri' }} ({{ $periodeStr }}) sebesar Rp {{ number_format($totalNominal, 0, ',', '.') }} - Status: LUNAS">
    @if(!empty($cloudinaryUrl))
    <meta property="og:image" content="{{ $cloudinaryUrl }}">
    @else
    <meta property="og:image" content="{{ asset('LOGOTPQ.png') }}">
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        body {
            background: linear-gradient(135deg, #064e3b 0%, #065f46 40%, #047857 100%);
            min-height: 100vh;
            padding: 24px 16px 40px;
            color: #0f172a;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .container {
            width: 100%;
            max-width: 580px;
        }
        .badge-verified {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #ffffff;
            font-size: 12px;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 9999px;
            margin-bottom: 16px;
        }
        .card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.8);
        }
        .card-header {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .logo-img {
            width: 54px;
            height: 54px;
            object-fit: contain;
            border-radius: 12px;
            background: #ffffff;
            padding: 4px;
            border: 1px solid #e2e8f0;
        }
        .header-title h1 {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.3px;
        }
        .header-title p {
            font-size: 12px;
            color: #64748b;
            margin-top: 2px;
        }
        .status-banner {
            padding: 12px 24px;
            font-size: 13px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .status-valid {
            background: #ecfdf5;
            color: #065f46;
            border-bottom: 1px solid #a7f3d0;
        }
        .status-cancelled {
            background: #fef2f2;
            color: #991b1b;
            border-bottom: 1px solid #fecaca;
        }
        .card-body {
            padding: 24px;
        }
        .receipt-image-wrapper {
            margin-bottom: 24px;
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            background: #f1f5f9;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        .receipt-image {
            width: 100%;
            height: auto;
            display: block;
            border-radius: 14px;
        }
        .info-grid {
            display: flex;
            flex-direction: column;
            gap: 12px;
            background: #f8fafc;
            border-radius: 14px;
            padding: 18px;
            border: 1px solid #f1f5f9;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            font-size: 13px;
            gap: 12px;
        }
        .info-label {
            color: #64748b;
            font-weight: 500;
            flex-shrink: 0;
        }
        .info-val {
            color: #0f172a;
            font-weight: 700;
            text-align: right;
            word-break: break-word;
        }
        .info-total {
            border-top: 2px dashed #cbd5e1;
            padding-top: 14px;
            margin-top: 4px;
        }
        .info-total .info-val {
            font-size: 18px;
            color: #047857;
            font-weight: 800;
        }
        .actions {
            margin-top: 24px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 13px 20px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
        }
        .btn-primary {
            background: #047857;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(4, 120, 87, 0.3);
        }
        .btn-primary:hover {
            background: #065f46;
        }
        .btn-outline {
            background: #ffffff;
            color: #334155;
            border: 1px solid #cbd5e1;
        }
        .btn-outline:hover {
            background: #f8fafc;
            border-color: #94a3b8;
        }
        .footer-note {
            margin-top: 20px;
            text-align: center;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.75);
            line-height: 1.6;
        }
        .lock-icon {
            display: inline-block;
            vertical-align: middle;
            margin-right: 4px;
        }
    </style>
</head>
<body>

    <div class="container">
        <!-- Badge Keabsahan Dokumen Digital -->
        <div style="text-align: center;">
            <div class="badge-verified">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <span>Dokumen Digital Resmi Terverifikasi</span>
            </div>
        </div>

        <div class="card">
            <!-- Kop Instansi -->
            <div class="card-header">
                <img src="{{ asset('LOGOTPQ.png') }}" alt="Logo TPQ" class="logo-img" onerror="this.style.display='none'">
                <div class="header-title">
                    <h1>{{ $namaInstansi }}</h1>
                    <p>Sistem Administrasi & Keuangan Terpadu</p>
                </div>
            </div>

            <!-- Status Banner -->
            @if($isValid)
            <div class="status-banner status-valid">
                <div style="display:flex; align-items:center; gap:8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    <span>PEMBAYARAN LUNAS (SAH)</span>
                </div>
                <span style="font-size:11px; opacity:0.85;">{{ $pembayaran->tanggal_bayar?->format('d/m/Y H:i') }} WIB</span>
            </div>
            @else
            <div class="status-banner status-cancelled">
                <div style="display:flex; align-items:center; gap:8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    <span>TRANSAKSI DIBATALKAN</span>
                </div>
                <span style="font-size:11px;">Batal</span>
            </div>
            @endif

            <div class="card-body">
                <!-- Jika ada gambar di Cloudinary, tampilkan gambar kuitansi resmi -->
                @if(!empty($cloudinaryUrl))
                <div class="receipt-image-wrapper">
                    <img src="{{ $cloudinaryUrl }}" alt="Kuitansi Resmi SPP" class="receipt-image" loading="lazy">
                </div>
                @endif

                <!-- Rincian Data Transaksi (Teks Resmi) -->
                <div class="info-grid">
                    <div class="info-row">
                        <span class="info-label">No. Kuitansi</span>
                        <span class="info-val" style="font-family: monospace; letter-spacing: 0.5px;">{{ $pembayaran->nomor_kuitansi }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Nama Santri</span>
                        <span class="info-val">{{ $siswa?->nama_lengkap ?? '-' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Kelas / Jilid</span>
                        <span class="info-val">{{ $siswa?->kelas?->nama_kelas ?? '-' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Wali Santri (HP)</span>
                        <span class="info-val">{{ $maskedPhone }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Periode SPP</span>
                        <span class="info-val" style="color: #0369a1;">{{ $periodeStr }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Metode Pembayaran</span>
                        <span class="info-val">{{ $pembayaran->metode_pembayaran }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Bendahara Penerima</span>
                        <span class="info-val">{{ $namaBendahara }}</span>
                    </div>
                    @if(!empty($pembayaran->catatan))
                    <div class="info-row">
                        <span class="info-label">Catatan</span>
                        <span class="info-val" style="font-style: italic; font-weight: normal;">{{ $pembayaran->catatan }}</span>
                    </div>
                    @endif
                    <div class="info-row info-total">
                        <span class="info-label" style="font-size: 14px; font-weight: 700; color: #0f172a;">Total Terbayar</span>
                        <span class="info-val">Rp {{ number_format($totalNominal, 0, ',', '.') }}</span>
                    </div>
                </div>

                <!-- Tombol Aksi -->
                <div class="actions">
                    @if(!empty($cloudinaryUrl))
                    <a href="{{ $cloudinaryUrl }}" target="_blank" download="Kuitansi-{{ $pembayaran->nomor_kuitansi }}.png" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        <span>Unduh Dokumen Gambar (Asli)</span>
                    </a>
                    @endif

                    @if(!empty($kontakBendahara))
                    @php
                        $cleanKontak = preg_replace('/[^0-9]/', '', $kontakBendahara);
                        if (str_starts_with($cleanKontak, '0')) {
                            $cleanKontak = '62' . substr($cleanKontak, 1);
                        }
                        $waMsg = urlencode("Assalamu'alaikum Bendahara TPQ, saya ingin konfirmasi bukti pembayaran kuitansi No: {$pembayaran->nomor_kuitansi} ananda {$siswa?->nama_lengkap}.");
                    @endphp
                    <a href="https://wa.me/{{ $cleanKontak }}?text={{ $waMsg }}" target="_blank" class="btn btn-outline">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        <span>Hubungi Bendahara TPQ</span>
                    </a>
                    @endif
                </div>
            </div>
        </div>

        <!-- Footer Keamanan & Keabsahan -->
        <div class="footer-note">
            <p>
                <svg class="lock-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Tautan ini dilindungi secara kriptografis & diterbitkan secara otomatis oleh Sistem Digital {{ $namaInstansi }}.
            </p>
            <p style="margin-top: 4px; opacity: 0.6;">© {{ date('Y') }} {{ $namaInstansi }}. Seluruh hak cipta dilindungi.</p>
        </div>
    </div>

</body>
</html>

