# Spesifikasi Desain: Role Struktural & Pengajar TPQ

**Tanggal:** 18 Oktober 2026  
**Status:** Disetujui  
**Aplikasi:** Sistem Terpadu Absensi & Pembayaran SPP Santri (Laravel 13 + Vue 3 + Tailwind CSS v4)

---

## 1. Latar Belakang & Masalah
Pada Taman Pendidikan Al-Qur'an (TPQ) dan pesantren, struktur kepengurusan memiliki karakteristik khusus:
1. **Kepala TPQ**: Pejabat struktural tertinggi yang berfokus pada pengawasan, supervisi kebijakan, evaluasi kehadiran santri, dan laporan keuangan SPP. Kepala TPQ **tidak mengajar** kelas apa pun.
2. **Sekretaris**: Mengelola urusan administrasi (data induk santri, kalender agenda & hari libur, absensi harian), sekaligus **merangkap mengajar** dan menjadi **Wali Kelas/Jilid**.
3. **Bendahara**: Mengelola kasir pembayaran SPP, tagihan, broadcast WhatsApp pengingat tunggakan, dan audit kuitansi, sekaligus **merangkap mengajar** dan menjadi **Wali Kelas/Jilid**.
4. **Guru / Ustadzah**: Pengajar murni yang memegang kelas/jilid binaan dan memantau presensi santri tanpa dibebani urusan kas SPP atau konfigurasi teknis.
5. **Administrator**: Mengelola aspek teknis IT, sinkronisasi gateway (Fonnte/Telegram), master jilid, dan konfigurasi jam operasional.
6. **Siswa / Santri**: Mengakses portal santri untuk melihat kartu QR dan riwayat kehadiran pribadi.

---

## 2. Arsitektur Data & Model Backend (Laravel)

### 2.1 Skema Data Pengguna (`users`)
Struktur tabel `users` memanfaatkan kombinasi:
- Kolom `role`: `enum('admin', 'kepala', 'sekretaris', 'bendahara', 'guru', 'siswa')`
- Kolom `id_kelas`: `foreignId('id_kelas')->nullable()->constrained('kelas')`

```php
// Contoh pemetaan akun:
// Kepala: role = 'kepala', id_kelas = null (tidak mengajar)
// Sekretaris: role = 'sekretaris', id_kelas = 1 (mengajar Jilid 1)
// Bendahara: role = 'bendahara', id_kelas = 2 (mengajar Jilid 2)
// Guru: role = 'guru', id_kelas = 3 (mengajar Jilid 3)
// Admin: role = 'admin', id_kelas = null
```

### 2.2 Data Akun Default ([DatabaseSeeder.php](file:///c:/laragon/www/project-ta/database/seeders/DatabaseSeeder.php))
| Peran | Username | Password | Status Mengajar | Hak Akses Utama |
| :--- | :--- | :--- | :--- | :--- |
| **Administrator** | `admin` | `admin123` | *Tidak mengajar* | Akses penuh pengaturan teknis & konfigurasi |
| **Kepala TPQ** | `kepala` | `kepala123` | *Tidak mengajar* | Supervisi & Laporan Absensi + SPP |
| **Sekretaris** | `sekretaris` | `sekretaris123` | **Wali Jilid 1** | Manajemen santri, agenda libur, absensi |
| **Bendahara** | `bendahara` | `bendahara123` | **Wali Jilid 2** | Kasir SPP, tagihan, WA gateway, absensi |
| **Guru / Pengajar**| `guru` | `guru123` | **Wali Jilid 3** | Scanner presensi, monitoring & rekap absensi |
| **Santri / Siswa** | `1001` | `1001` | Santri | Kartu QR santri & kehadiran pribadi |

### 2.3 Proteksi API Backend ([routes/api.php](file:///c:/laragon/www/project-ta/routes/api.php))
- **SPP & Keuangan**:
  - Operasional Kasir & Bayar: `['admin', 'bendahara']`
  - Supervisi & Riwayat: `['admin', 'bendahara', 'kepala']`
- **Administrasi Santri & Agenda**:
  - Mutasi / Tambah / Import / Edit Santri & Agenda Libur: `['admin', 'sekretaris']`
  - Read-Only / Lihat Data Santri: `['admin', 'kepala', 'sekretaris', 'bendahara', 'guru']`
- **Absensi & Presensi**:
  - Scanner QR & Update Status Kehadiran: `['admin', 'kepala', 'sekretaris', 'bendahara', 'guru']`
- **Konfigurasi Sistem & Master Jilid**:
  - Khusus `['admin']`

---

## 3. Desain Frontend & Hak Akses Menu (Vue 3)

### 3.1 Permission Helpers ([useAuth.js](file:///c:/laragon/www/project-ta/resources/js/composables/useAuth.js))
Helper reaktif terpusat untuk memvalidasi hak akses komponen:
- `isAdmin`: Pengecekan role `admin`
- `isKepala`: Pengecekan role `kepala`
- `isSekretaris`: Pengecekan role `sekretaris`
- `isBendahara`: Pengecekan role `bendahara`
- `isGuru`: Pengecekan role `guru`
- `isSiswa`: Pengecekan role `siswa`
- `canManageSpp`: `['admin', 'bendahara']`
- `canViewSppReports`: `['admin', 'bendahara', 'kepala']`
- `canManageSantri`: `['admin', 'sekretaris']`
- `canManageAgenda`: `['admin', 'sekretaris']`
- `canManageSettings`: `['admin']`
- `canDoAbsensi`: `['admin', 'kepala', 'sekretaris', 'bendahara', 'guru']`

### 3.2 Matriks Menu Sidebar ([AppLayout.vue](file:///c:/laragon/www/project-ta/resources/js/layouts/AppLayout.vue))
| Menu Navigasi | Admin | Kepala | Sekretaris | Bendahara | Guru | Siswa |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| **Dashboard** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Scanner Presensi QR** | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Monitoring Realtime** | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Rekap Matrix 1-31 Hari** | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Riwayat Absensi** | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Data Santri & QR Card** | ✅ (Kelola) | 👁️ (Lihat) | ✅ (Kelola) | 👁️ (Lihat) | 👁️ (Lihat) | ❌ |
| **Agenda & Hari Libur** | ✅ (Kelola) | 👁️ (Lihat) | ✅ (Kelola) | 👁️ (Lihat) | 👁️ (Lihat) | ❌ |
| **Kasir / Bayar SPP** | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ |
| **Tagihan & Tunggakan SPP** | ✅ | ✅ (Supervisi) | ❌ | ✅ | ❌ | ❌ |
| **Input Cepat SPP (Batch)** | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ |
| **Broadcast Pengingat WA** | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ |
| **Riwayat Kuitansi SPP** | ✅ | ✅ (Laporan) | ❌ | ✅ | ❌ | ❌ |
| **Master Jilid & Tarif SPP**| ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Pengaturan Sistem** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |

### 3.3 Indikator Profil Sidebar
Kotak profil di sidebar menampilkan lencana peran dan jilid binaan:
- **Kepala**: `👑 Kepala TPQ` (tanpa keterangan kelas karena tidak mengajar)
- **Sekretaris**: `📝 Sekretaris` + badge `Wali Jilid 1`
- **Bendahara**: `💰 Bendahara` + badge `Wali Jilid 2`
- **Guru**: `📖 Pengajar` + badge `Wali Jilid 3`
- **Admin**: `⚙️ Administrator`
- **Santri**: `🎓 Santri`

### 3.4 Router Guard ([router/index.js](file:///c:/laragon/www/project-ta/resources/js/router/index.js))
Setiap rute yang memiliki batasan wewenang dilengkapi metadata `meta: { roles: [...] }`. Jika diakses paksa melalui pengetikan URL browser oleh role yang tidak berhak, navigasi dibatalkan dan diarahkan ke `/dashboard` disertai notifikasi toast peringatan akses.

### 3.5 Demo 1-Klik ([LoginView.vue](file:///c:/laragon/www/project-ta/resources/js/views/auth/LoginView.vue))
Tombol cepat pengisian akun untuk 6 peran demo disediakan di layar login agar pengguna dapat melakukan demonstrasi peran secara praktis.

---

## 4. Rencana Pengujian
1. **Unit & Feature Testing (PHPUnit)**:
   - Pengujian autentikasi untuk masing-masing role (`admin`, `kepala`, `sekretaris`, `bendahara`, `guru`, `siswa`).
   - Pengujian otorisasi endpoint kasir SPP: berhasil untuk `bendahara` dan `admin`, ditolak (403) untuk `guru` dan `sekretaris`.
   - Pengujian endpoint pengaturan: hanya dapat diakses oleh `admin`.
2. **Frontend Testing & Build**:
   - Memastikan `npm run build` berhasil 100% tanpa komplain type/import.
   - Memastikan router guard bekerja dengan benar pada perpindahan route antar peran.
