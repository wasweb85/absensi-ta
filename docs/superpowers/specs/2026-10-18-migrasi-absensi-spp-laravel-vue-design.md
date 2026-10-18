# Desain & Spesifikasi Migrasi Sistem Terpadu Absensi & SPP Santri
**Basis Teknologi:** Laravel 13 + MySQL + Vue 3 (Composition API) + Tailwind CSS v4 + Vite  
**Tanggal:** 18 Oktober 2026  
**Status:** Disetujui (Approved by User)

---

## 1. Ringkasan Eksekutif & Tujuan Proyek

Proyek ini adalah migrasi dan unifikasi dua sistem berbasis Google Apps Script + Google Spreadsheet ke dalam satu aplikasi web berbasis Laravel dan Vue 3 untuk kebutuhan skripsi:
1. **Sistem Acuan 1 (`Code.gs` & `index.html`)**: Sistem Absensi Sekolah/Santri (Smart Attendance System dengan Scanner QR/Barcode, Realtime Monitoring, Kalender Libur/Agenda, dan Notifikasi Telegram/WA).
2. **Sistem Acuan 2 (`codespp.gs` & `sppindex.html`)**: Sistem Informasi SPP Santri (Master Jilid/Kelas, Tagihan Bulanan Otomatis, Transaksi Pembayaran & Kuitansi, Kalkulasi Tunggakan, Input Cepat SPP, dan Broadcast WhatsApp via Fonnte/wa.me).

### Sasaran Utama
- **Single Source of Truth**: Data siswa/santri dan kelas/jilid disatukan menjadi satu entitas relasional terpusat di MySQL.
- **Single Unified Portal**: Satu antarmuka responsif dengan Single Sign-On (SSO) untuk multi-role (Admin, Guru/Wali Jilid, Siswa/Wali).
- **Zero Loss of Business Logic**: Mempertahankan 100% aturan bisnis, kalkulasi jam keterlambatan, logika tap-pulang, perhitungan akumulasi tunggakan SPP, template pesan WhatsApp, dan rekap matrix absensi.

---

## 2. Arsitektur Sistem

```
+-----------------------------------------------------------------------------------+
|                           FRONTEND VUE 3 (SPA)                                    |
|                                                                                   |
|  +-----------------------------------------------------------------------------+  |
|  |                  App Shell / Layout (Sidebar + Topbar)                      |  |
|  +-----------------------------------------------------------------------------+  |
|  |  [Dashboard]      |  [Modul Absensi]            |  [Modul SPP]              |  |
|  |  - Statistik Live |  - QR Scanner (Kamera/USB)  |  - Kasir Pembayaran       |  |
|  |  - Pemasukan SPP  |  - Realtime Monitoring      |  - Tagihan & Tunggakan    |  |
|  |  - Grafik Bulanan |  - Rekap Matrix 1-31 Hari   |  - Input Cepat (Batch)    |  |
|  |                   |  - Agenda & Hari Libur      |  - Broadcast WhatsApp     |  |
|  |                   |  - Data Siswa & Guru        |  - Laporan Keuangan       |  |
|  +-----------------------------------------------------------------------------+  |
+------------------------------------------+----------------------------------------+
                                           | REST API (JSON / Bearer Token / Cookie)
                                           v
+-----------------------------------------------------------------------------------+
|                           BACKEND LARAVEL 13                                      |
|                                                                                   |
|  [Controllers & Form Requests]                                                    |
|   ├── AuthController (Login multi-role: Admin, Guru, Siswa)                       |
|   ├── SiswaController & KelasController (Shared Master Data)                      |
|   ├── AbsensiController (Scan engine, realtime status, matrix report)             |
|   ├── SppController (Tagihan generator, transaksi bayar, pembatalan, tunggakan)  |
|   ├── AgendaLiburController (Jadwal kegiatan & hari libur)                        |
|   └── SettingController (Konfigurasi jam, bot Telegram, token Fonnte)             |
|                                                                                   |
|  [Services & Business Logic]                                                      |
|   ├── AbsensiService (Penetapan status Hadir/Terlambat/Pulang berdasarkan jam)    |
|   ├── SppBillingService (Kalkulasi tagihan kalender akademik Juli-Juni / Jan-Des) |
|   ├── WhatsAppNotificationService (Fonnte API & generator link wa.me)             |
|   └── ExportService (Rekap Matrix Excel & Cetak Kuitansi)                         |
+------------------------------------------+----------------------------------------+
                                           | Eloquent ORM
                                           v
+-----------------------------------------------------------------------------------+
|                           DATABASE MYSQL (Laragon)                                |
|   users | kelas | siswa | absensi | hari_libur | agenda | tagihan_spp |           |
|   pembayaran_spp | pengaturan                                                     |
+-----------------------------------------------------------------------------------+
```

---

## 3. Skema Database Relasional

### 3.1. Tabel `users`
Mengelola autentikasi login terpusat.
- `id` (PK, BigInt, Auto-Increment)
- `name` (String)
- `username` (String, Unique)
- `email` (String, Nullable)
- `password` (String, Hashed)
- `role` (Enum: `'admin'`, `'guru'`, `'siswa'`)
- `id_kelas` (Foreign Key ke `kelas.id`, Nullable - untuk role guru/wali jilid)
- `nisn` (String, Nullable, Index - untuk role siswa)
- `remember_token`, `created_at`, `updated_at`

### 3.2. Tabel `kelas`
Master kelas / jilid yang dipakai bersama untuk Absensi dan SPP.
- `id` (PK, BigInt, Auto-Increment)
- `nama_kelas` (String, e.g. "JILID 1", "JILID 2", dst.)
- `nominal_spp` (Decimal 12,2 - tarif SPP per bulan untuk kelas ini)
- `wali_kelas` (String, Nullable)
- `no_hp_wali` (String, Nullable - untuk notifikasi rekap tunggakan jilid)
- `warna_label` (String, e.g. "#f44336" untuk Jilid 1, "#e91e63" untuk Jilid 2)
- `urutan` (Integer, Default 1)
- `created_at`, `updated_at`

### 3.3. Tabel `siswa`
Data santri/siswa tunggal untuk absensi dan keuangan.
- `id` (PK, BigInt, Auto-Increment)
- `nisn` (String, Unique, Index)
- `nama_lengkap` (String)
- `jenis_kelamin` (Enum: `'L'`, `'P'`)
- `id_kelas` (FK ke `kelas.id`)
- `no_hp_ortu` (String, Nullable - tujuan WhatsApp notifikasi)
- `nama_ortu` (String, Nullable)
- `alamat` (Text, Nullable)
- `status` (Enum: `'aktif'`, `'nonaktif'`, `'lulus'`, `'pindah'`, Default `'aktif'`)
- `foto` (String, Nullable)
- `created_at`, `updated_at`

### 3.4. Tabel `absensi`
Pencatatan absensi harian santri.
- `id` (PK, BigInt, Auto-Increment)
- `id_siswa` (FK ke `siswa.id`)
- `tanggal` (Date, Index)
- `waktu_masuk` (Time, Nullable)
- `waktu_pulang` (Time, Nullable)
- `status` (Enum: `'Hadir'`, `'Terlambat'`, `'Sakit'`, `'Izin'`, `'Alpa'`)
- `metode_scan` (Enum: `'qr_kamera'`, `'scanner_usb'`, `'manual'`, Default `'qr_kamera'`)
- `keterangan` (String, Nullable)
- `created_at`, `updated_at`
- *Unique Constraint*: `[id_siswa, tanggal]` (1 siswa hanya memiliki 1 catatan absensi per tanggal)

### 3.5. Tabel `hari_libur`
- `id` (PK, BigInt, Auto-Increment)
- `tanggal_mulai` (Date)
- `tanggal_selesai` (Date)
- `keterangan` (String)
- `created_at`, `updated_at`

### 3.6. Tabel `agenda`
- `id` (PK, BigInt, Auto-Increment)
- `tanggal` (Date)
- `judul` (String)
- `keterangan` (Text, Nullable)
- `created_at`, `updated_at`

### 3.7. Tabel `tagihan_spp`
Tagihan bulanan santri.
- `id` (PK, BigInt, Auto-Increment)
- `id_siswa` (FK ke `siswa.id`)
- `bulan` (Integer, 1 s/d 12)
- `tahun` (Integer, e.g. 2026)
- `tahun_ajaran` (String, e.g. "2026/2027")
- `nominal_tagihan` (Decimal 12,2)
- `status` (Enum: `'belum_lunas'`, `'lunas'`, `'kurang_bayar'`, Default `'belum_lunas'`)
- `jatuh_tempo` (Date, Nullable)
- `created_at`, `updated_at`
- *Unique Constraint*: `[id_siswa, bulan, tahun]`

### 3.8. Tabel `pembayaran_spp`
Pencatatan riwayat transaksi dan penerimaan kuitansi.
- `id` (PK, BigInt, Auto-Increment)
- `id_tagihan` (FK ke `tagihan_spp.id`)
- `id_siswa` (FK ke `siswa.id`)
- `nomor_kuitansi` (String, Unique, Index, e.g. "KW-202610-001")
- `tanggal_bayar` (DateTime)
- `jumlah_bayar` (Decimal 12,2)
- `metode_pembayaran` (Enum: `'Tunai'`, `'Transfer'`)
- `id_petugas` (FK ke `users.id`)
- `status_transaksi` (Enum: `'valid'`, `'dibatalkan'`, Default `'valid'`)
- `alasan_pembatalan` (Text, Nullable - untuk kebutuhan audit trail)
- `dibatalkan_oleh` (String, Nullable)
- `catatan` (String, Nullable)
- `created_at`, `updated_at`

### 3.9. Tabel `pengaturan`
Konfigurasi fleksibel sistem berbasis key-value.
- `key` (String, Primary Key)
- `value` (Text, Nullable)
- Contoh keys:
  - `nama_instansi`: "TPQ / Pondok Pesantren Baitul Qur'an"
  - `jam_masuk`: "07:00"
  - `jam_terlambat`: "07:15"
  - `jam_pulang`: "12:00"
  - `tahun_ajaran_aktif`: "2026/2027"
  - `fonnte_token`: "TOKEN_API_WHATSAPP"
  - `telegram_bot_token`: "BOT_TOKEN"
  - `telegram_chat_id`: "CHAT_ID"

---

## 4. Logika Bisnis & Mesin Pemroses

### 4.1. Mesin Absensi & Scanner
1. **Verifikasi Waktu Scan**:
   - Jika `Waktu_Scan < jam_masuk` sampai `jam_terlambat` -> Status: **Hadir**.
   - Jika `Waktu_Scan > jam_terlambat` dan `< jam_pulang` -> Status: **Terlambat**.
   - Jika sudah ada riwayat masuk di hari yang sama, dan `Waktu_Scan >= jam_pulang` -> Update `waktu_pulang` (**Tap Pulang**).
2. **Scanner Eksternal & Kamera**:
   - Mendukung scan via kamera laptop/HP menggunakan library HTML5-QRCode.
   - Mendukung scan via barcode/RFID scanner USB (keyboard wedge input dengan deteksi `Enter`).
3. **Pengecekan Hari Libur**:
   - Sistem otomatis memeriksa apakah tanggal scan bertepatan dengan tanggal di tabel `hari_libur`.
4. **Rekap Matrix 1-31**:
   - Menyusun matriks data kehadiran seluruh siswa dari tanggal 1 hingga akhir bulan berjalan (kode: `H`, `T`, `S`, `I`, `A`) lengkap dengan persentase kehadiran.

### 4.2. Mesin SPP & Keuangan
1. **Generator Tagihan**:
   - Menghasilkan tagihan untuk semua santri aktif pada bulan/tahun yang dipilih dengan nominal mengacu pada `kelas.nominal_spp`.
   - Menjamin *idempotency* (tidak membuat tagihan ganda jika tagihan bulan tersebut sudah ada).
2. **Perhitungan Tunggakan Realtime**:
   - Mengakumulasi seluruh tagihan santri yang statusnya `belum_lunas` atau `kurang_bayar` hingga bulan cut-off.
3. **Pembayaran Transaksional**:
   - Eksekusi menggunakan `DB::transaction` untuk mencegah inkonsistensi saldo.
   - Mengubah status tagihan menjadi `lunas` atau `kurang_bayar`.
   - Mengeluarkan nomor kuitansi unik dan data siap cetak.
4. **Pembatalan Pembayaran (Audit Trail)**:
   - Pembatalan transaksi mencatat alasan pembatalan dan nama petugas, serta mengembalikan status tagihan terkait menjadi `belum_lunas`.
5. **WhatsApp Generator & Blast (Fonnte & wa.me)**:
   - Template Japri Orang Tua: Menyebutkan nama santri, bulan tunggakan, dan rincian total nominal.
   - Template Grup Jilid: Pengumuman SPP bulanan kelas.
   - Template Rekap Wali Jilid: Ringkasan persentase pembayaran dan daftar santri menunggak di jilidnya.

---

## 5. Rencana Pembagian 6 Task Implementasi

1. **Task 1: Desain & Migrasi Database MySQL**
   - Setup `.env` untuk MySQL Laragon (`project_ta`).
   - Buat migration untuk semua tabel (`kelas`, `siswa`, `absensi`, `hari_libur`, `agenda`, `tagihan_spp`, `pembayaran_spp`, `pengaturan`).
   - Buat Model Eloquent dengan relasi lengkap (`Kelas`, `Siswa`, `Absensi`, `TagihanSpp`, `PembayaranSpp`, `HariLibur`, `Agenda`, `Pengaturan`).
   - Buat DatabaseSeeder dengan data awal (Admin, Guru contoh, Jilid 1 - 6, tarif SPP, konfigurasi jam kerja).

2. **Task 2: Backend API & Service Logic**
   - Setup API Controllers: `AuthController`, `SiswaController`, `KelasController`, `AbsensiController`, `SppController`, `AgendaController`, `SettingController`.
   - Setup Business Services: `AbsensiScannerService`, `SppBillingService`, `WhatsAppNotificationService`.
   - Definisikan API Routes di `routes/api.php`.

3. **Task 3: Layout & Auth Shell Vue 3**
   - Setup Vue Router (`resources/js/router/index.js`) dengan rute `/login`, `/dashboard`, `/absensi/*`, `/spp/*`, `/pengaturan`.
   - Desain `AppLayout.vue` dengan sidebar navigasi modern, status badge, dark mode, dan responsif mobile.
   - Halaman `Login.vue` terpadu dengan autentikasi multi-role.

4. **Task 4: Frontend Modul Absensi**
   - `Scanner.vue`: Scanner kamera (HTML5-QRCode) & input cepat RFID/Barcode scanner.
   - `MonitoringRealtime.vue`: Kartu metrik live dan tabel status absensi per kelas.
   - `SiswaManagement.vue`: Kelola data santri, filter jilid, cetak kartu QR/Barcode.
   - `RekapMatrix.vue`: Laporan absensi matrix 1-31 hari dengan opsi cetak/export Excel.
   - `AgendaLibur.vue`: Kalender agenda dan hari libur.

5. **Task 5: Frontend Modul SPP**
   - `KasirPembayaran.vue`: Transaksi bayar SPP cepat, riwayat bayar, dan modal cetak kuitansi.
   - `TagihanTunggakan.vue`: Monitoring tagihan bulanan dan status lunas/tunggakan santri.
   - `InputCepatSPP.vue`: Batch pembayaran multi-santri dalam satu form tabel.
   - `PengingatWa.vue`: Kirim WhatsApp blast via Fonnte API atau tautan cepat wa.me ke orang tua dan wali jilid.
   - `PengaturanSpp.vue`: Pengaturan nominal SPP per jilid/kelas.

6. **Task 6: Dashboard Terpadu, Laporan Finansial & Pengujian Akhir**
   - `DashboardOverview.vue`: Menggabungkan metrik kehadiran hari ini dan statistik keuangan SPP bulan berjalan dengan Chart.js.
   - Pengujian fitur dan verifikasi build Vite (`npm run build`).
   - Menjalankan PHPUnit test suite.
