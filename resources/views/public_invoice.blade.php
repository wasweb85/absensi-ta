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
    <meta property="og:description" content="Kuitansi {{ $nomorKuitansiDisplay ?? $pembayaran->nomor_kuitansi }} ananda {{ $siswa?->nama_lengkap ?? 'Santri' }} ({{ $periodeStr }}) sebesar Rp {{ number_format($totalNominal, 0, ',', '.') }} - Status: LUNAS">
    @if(!empty($cloudinaryUrl))
    <meta property="og:image" content="{{ $cloudinaryUrl }}">
    @else
    <meta property="og:image" content="{{ asset('LOGOTPQ.png') }}">
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            background-color: #f8fafc;
            min-height: 100vh;
            padding: 24px 16px 48px;
            color: #1e293b;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .container {
            width: 100%;
            max-width: 460px;
        }
        .card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04), 0 10px 20px -5px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }
        .card-top-accent {
            height: 4px;
            background: linear-gradient(90deg, #059669 0%, #10b981 100%);
        }
        .card-header {
            padding: 20px 22px 16px;
            display: flex;
            align-items: center;
            gap: 14px;
            border-bottom: 1px solid #f1f5f9;
        }
        .logo-img {
            width: 44px;
            height: 44px;
            object-fit: contain;
            border-radius: 10px;
            background: #ffffff;
            padding: 2px;
            border: 1px solid #e2e8f0;
            flex-shrink: 0;
        }
        .header-title h1 {
            font-size: 15px;
            font-weight: 600;
            color: #0f172a;
            line-height: 1.3;
            letter-spacing: -0.2px;
        }
        .header-title p {
            font-size: 12px;
            color: #64748b;
            font-weight: 400;
            margin-top: 2px;
        }

        /* Hero: Status & Total Amount */
        .hero-section {
            padding: 20px 22px 18px;
            border-bottom: 1px solid #f1f5f9;
        }
        .hero-status-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11.5px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 9999px;
            letter-spacing: 0.3px;
        }
        .status-pill-success {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }
        .status-pill-danger {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        .hero-date {
            font-size: 12px;
            color: #94a3b8;
            font-weight: 400;
        }
        .hero-total-block {
            margin-top: 14px;
        }
        .hero-total-label {
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #64748b;
        }
        .hero-total-val {
            font-size: 26px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 2px;
            font-feature-settings: 'tnum';
            letter-spacing: -0.5px;
        }

        /* Receipt Information Rows */
        .receipt-body {
            padding: 16px 22px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 9px 0;
            font-size: 13px;
            gap: 16px;
            border-bottom: 1px solid #f8fafc;
        }
        .info-row:last-child {
            border-bottom: none;
            padding-bottom: 4px;
        }
        .info-label {
            color: #64748b;
            font-weight: 400;
            flex-shrink: 0;
            min-width: 110px;
        }
        .info-val {
            color: #334155;
            font-weight: 500;
            text-align: right;
            word-break: break-word;
        }
        .info-val-highlight {
            color: #0f172a;
            font-weight: 600;
        }
        .info-val-badge {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 12px;
            font-weight: 500;
            color: #334155;
            background: #f1f5f9;
            padding: 2px 7px;
            border-radius: 6px;
            letter-spacing: 0.3px;
        }

        /* Periode & Multi-Month Breakdown */
        .periode-container {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 4px;
            max-width: 72%;
        }
        .periode-details {
            width: 100%;
            text-align: right;
            margin-top: 2px;
        }
        .periode-details summary {
            list-style: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            font-weight: 500;
            color: #047857;
            background: #f0fdf4;
            padding: 3px 8px;
            border-radius: 6px;
            cursor: pointer;
            border: 1px solid #bbf7d0;
            transition: all 0.15s ease;
            user-select: none;
        }
        .periode-details summary::-webkit-details-marker {
            display: none;
        }
        .periode-details summary:hover {
            background: #dcfce7;
        }
        .periode-details[open] summary svg {
            transform: rotate(180deg);
        }
        .bulan-breakdown-list {
            margin-top: 6px;
            display: flex;
            flex-direction: column;
            gap: 2px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px 12px;
            max-height: 180px;
            overflow-y: auto;
            text-align: left;
        }
        .bulan-breakdown-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11.5px;
            padding: 4px 0;
            border-bottom: 1px dashed #e2e8f0;
        }
        .bulan-breakdown-item:last-child {
            border-bottom: none;
        }
        .bulan-breakdown-label {
            font-weight: 400;
            color: #475569;
        }
        .bulan-breakdown-nominal {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-weight: 500;
            color: #047857;
        }

        /* Image Receipt Preview (if Cloudinary is attached) */
        .receipt-image-wrapper {
            margin: 0 22px 16px;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .receipt-image {
            width: 100%;
            height: auto;
            display: block;
        }

        /* Action Buttons */
        .actions {
            padding: 4px 22px 22px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.15s ease;
            border: none;
            min-height: 42px;
        }
        .btn-primary {
            background: #059669;
            color: #ffffff;
            font-weight: 600;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        .btn-primary:hover {
            background: #047857;
        }
        .btn-secondary {
            background: #ffffff;
            color: #334155;
            border: 1px solid #cbd5e1;
        }
        .btn-secondary:hover {
            background: #f8fafc;
            border-color: #94a3b8;
        }

        /* Footer */
        .footer-note {
            margin-top: 20px;
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
            line-height: 1.5;
            font-weight: 400;
        }

        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }
            .actions, .footer-note {
                display: none !important;
            }
            .card {
                box-shadow: none !important;
                border: 1px solid #cbd5e1 !important;
                border-radius: 0 !important;
            }
            .card-top-accent {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="card">
            <!-- Accent Top Stripe -->
            <div class="card-top-accent"></div>

            <!-- Kop Instansi -->
            <div class="card-header">
                <img src="{{ asset('LOGOTPQ.png') }}" alt="Logo TPQ" class="logo-img" onerror="this.style.display='none'">
                <div class="header-title">
                    <h1>{{ $namaInstansi }}</h1>
                    <p>Sistem Administrasi & Keuangan Terpadu</p>
                </div>
            </div>

            <!-- Hero: Status & Total -->
            <div class="hero-section">
                <div class="hero-status-row">
                    @if($isValid)
                    <div class="status-pill status-pill-success">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>PEMBAYARAN LUNAS (SAH)</span>
                    </div>
                    <span class="hero-date">{{ $pembayaran->tanggal_bayar?->format('d/m/Y H:i') }} WIB</span>
                    @else
                    <div class="status-pill status-pill-danger">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        <span>TRANSAKSI DIBATALKAN</span>
                    </div>
                    <span class="hero-date">Batal</span>
                    @endif
                </div>

                <div class="hero-total-block">
                    <div class="hero-total-label">Total Pembayaran</div>
                    <div class="hero-total-val">Rp {{ number_format($totalNominal, 0, ',', '.') }}</div>
                </div>
            </div>

            <!-- Rincian Data Transaksi (Teks Resmi) -->
            <div class="receipt-body">
                <div class="info-row">
                    <span class="info-label">No. Kuitansi</span>
                    <span class="info-val-badge">{{ $nomorKuitansiDisplay ?? $pembayaran->nomor_kuitansi }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Nama Santri</span>
                    <span class="info-val info-val-highlight">{{ $siswa?->nama_lengkap ?? '-' }}</span>
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
                    <div class="periode-container">
                        <span class="info-val info-val-highlight" style="color: #0369a1;">{{ $periodeStr }}</span>
                        @if(!empty($daftarBulan) && count($daftarBulan) > 1)
                        <details class="periode-details">
                            <summary>
                                <span>Rincian {{ count($daftarBulan) }} Bulan</span>
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="transition: transform 0.2s;"><polyline points="6 9 12 15 18 9"/></svg>
                            </summary>
                            <div id="daftarBulanBreakdown" class="bulan-breakdown-list">
                                @foreach($daftarBulan as $itemBulan)
                                <div class="bulan-breakdown-item">
                                    <span class="bulan-breakdown-label">{{ $itemBulan['label'] }}</span>
                                    <span class="bulan-breakdown-nominal">Rp {{ number_format($itemBulan['nominal'], 0, ',', '.') }}</span>
                                </div>
                                @endforeach
                            </div>
                        </details>
                        @endif
                    </div>
                </div>
                <div class="info-row">
                    <span class="info-label">Metode Bayar</span>
                    <span class="info-val">{{ $pembayaran->metode_pembayaran }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Bendahara Penerima</span>
                    <span class="info-val">{{ $namaBendahara }}</span>
                </div>
                @if(!empty($pembayaran->catatan))
                <div class="info-row">
                    <span class="info-label">Catatan</span>
                    <span class="info-val" style="font-style: italic; color: #64748b;">{{ $pembayaran->catatan }}</span>
                </div>
                @endif
            </div>

            <!-- Jika ada gambar di Cloudinary, tampilkan gambar kuitansi resmi -->
            @if(!empty($cloudinaryUrl))
            <div class="receipt-image-wrapper">
                <img src="{{ $cloudinaryUrl }}" alt="Kuitansi Resmi SPP" class="receipt-image" loading="lazy">
            </div>
            @endif

            <!-- Tombol Aksi -->
            <div class="actions">
                @if(!empty($cloudinaryUrl))
                <a href="{{ $cloudinaryUrl }}" target="_blank" download="Kuitansi-{{ $nomorKuitansiDisplay ?? $pembayaran->nomor_kuitansi }}.png" class="btn btn-primary">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <span>Unduh Gambar Kuitansi</span>
                </a>
                @endif

                <button type="button" onclick="window.print()" class="btn {{ empty($cloudinaryUrl) ? 'btn-primary' : 'btn-secondary' }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                    <span>Cetak / Simpan PDF</span>
                </button>

                @if(!empty($kontakBendahara))
                @php
                    $cleanKontak = preg_replace('/[^0-9]/', '', $kontakBendahara);
                    if (str_starts_with($cleanKontak, '0')) {
                        $cleanKontak = '62' . substr($cleanKontak, 1);
                    }
                    $waMsg = urlencode("Assalamu'alaikum Bendahara TPQ, saya ingin konfirmasi bukti pembayaran kuitansi No: " . ($nomorKuitansiDisplay ?? $pembayaran->nomor_kuitansi) . " ananda {$siswa?->nama_lengkap}.");
                @endphp
                <a href="https://wa.me/{{ $cleanKontak }}?text={{ $waMsg }}" target="_blank" class="btn btn-secondary">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    <span>Hubungi Bendahara TPQ</span>
                </a>
                @endif
            </div>
        </div>

        <!-- Footer Keamanan & Keabsahan -->
        <div class="footer-note">
            <p>Dokumen digital resmi diterbitkan otomatis oleh Sistem Informasi {{ $namaInstansi }}.</p>
            <p style="margin-top: 2px;">Terverifikasi secara digital & sah.</p>
        </div>
    </div>

</body>
</html>