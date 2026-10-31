# Desain Spesifikasi: Manajemen Wali Jilid Putra & Putri Fleksibel

## 1. Latar Belakang & Tujuan
Pada operasional lembaga TPQ (Taman Pendidikan Al-Qur'an), pembelajaran santri di tingkat awal (Jilid 1 & 2) umumnya dilakukan secara bersama/campur (anak usia dini/PAUD/TK). Namun mulai Jilid 3 ke atas, santri putra dan santri putri dipisahkan ke dalam halaqah/kelas yang berbeda:
- Santri Putra dibina oleh **Ustadz** (Guru Laki-laki).
- Santri Putri dibina oleh **Ustadzah** (Guru Perempuan).

Tujuan dari pembaruan ini adalah memberikan fleksibilitas pada sistem di mana **1 Jilid tetap utuh** (misalnya `JILID 3`, tidak perlu memecah nama jilid menjadi entitas database yang berbeda), namun dapat menampung **dua wali jilid sekaligus** (1 Wali Santri Putra dan 1 Wali Santri Putri), dengan pelaporan presensi serta tanda tangan pengesahan yang otomatis terpisah dan tepat sasaran.

---

## 2. Batasan Desain (Design Constraints)
1. **Tidak Menambah Card Berlebihan:** Tampilan UI tidak boleh menambahkan card-card baru yang memakan ruang. Seluruh indikator gender harus berupa dropdown atau badge ringkas pada toolbar/tabel yang sudah ada.
2. **Kesesuaian 1 Halaman A4 Landscape:** Format cetak laporan PDF/Print harus tetap pas dan rapi dalam satu lembar A4 landscape (Kop surat, tabel presensi, keterangan status mepet ke tabel, dan tanda tangan).
3. **Fleksibilitas Pengajar:** Ustadz/Ustadzah tetap dapat melihat data satu jilid utuh saat dibutuhkan untuk saling membantu absensi, namun secara bawaan tersaring sesuai kelompok binaannya.

---

## 3. Arsitektur & Perubahan Database

### 3.1 Migration Tabel `users`
Menambahkan kolom `gender_binaan` pada tabel `users`:
```php
Schema::table('users', function (Blueprint $table) {
    $table->string('gender_binaan', 10)->default('all')->after('id_kelas'); 
    // Nilai: 'all' (Campur/Semua), 'L' (Khusus Putra), 'P' (Khusus Putri)
});
```

### 3.2 Model & Relasi
- **`User` Model:**
  - Menambahkan `gender_binaan` ke dalam `$fillable` / `#[Fillable]`.
  - Casts default `'all'`.
- **`Kelas` Model:**
  - Accessor pembantu untuk menampilkan daftar nama wali secara lengkap:
    - Jika ada 1 wali campur: `"Ustadzah Fatimah"`
    - Jika ada 2 wali (putra & putri): `"Ustadz Fairus (Putra) & Ustadzah Dinil (Putri)"`
    - Kolom `wali_kelas` di tabel `kelas` diperbarui secara otomatis saat penugasan guru berubah.

---

## 4. Logika Backend & API

### 4.1 Manajemen Akun Guru (`UserController.php`)
- **Validasi Penugasan:**
  - Mengizinkan satu `id_kelas` dipegang oleh maksimal 2 guru, dengan syarat `gender_binaan` berbeda (satu `'L'` dan satu `'P'`).
  - Mencegah duplikasi: jika jilid sudah memiliki wali untuk gender yang sama (misal JILID 3 sudah memiliki wali `'L'`), sistem akan menolak penugasan guru kedua dengan error validasi yang jelas.
  - Jika jilid diatur `'all'` (campur), maka jilid tersebut hanya boleh dipegang 1 guru.

### 4.2 Endpoint Monitoring Presensi (`AbsensiController.php -> monitoring`)
- Menerima parameter opsional: `jenis_kelamin` (`'L'`, `'P'`, atau null/semua).
- Jika user ber-role `guru` dan memiliki `gender_binaan` ('L' atau 'P'), default filter gender mengarah ke gender binaannya jika parameter tidak disertakan.

### 4.3 Endpoint Rekap Matrix Presensi (`AbsensiController.php -> matrix`)
- Menerima parameter opsional: `jenis_kelamin` (`'L'`, `'P'`, atau null/semua).
- Data santri yang dimuat ke dalam matriks presensi disaring sesuai filter gender tersebut.
- Metadata respon menyertakan informasi wali kelas yang aktif:
  - Jika filter `'L'`: mengembalikan nama ustadz pembina putra.
  - Jika filter `'P'`: mengembalikan nama ustadzah pembina putri.
  - Jika `'all'` / campur: mengembalikan nama wali jilid terkait.

---

## 5. Rancangan Antarmuka Pengguna (Frontend)

### 5.1 Data Guru & Pengurus (`GuruView.vue`)
- **Formulir Guru:**
  - Di bawah pilihan `Wali Jilid Binaan`, jika jilid dipilih, muncul dropdown ringkas:
    - `Kelompok Binaan`:
      - `Semua Santri / Campur (Jilid 1 & 2)` (`'all'`)
      - `Khusus Santri Putra` (`'L'`)
      - `Khusus Santri Putri` (`'P'`)
- **Tabel Daftar Guru:**
  - Badge jilid menampilkan info kelompok binaan tanpa card tambahan:
    - `JILID 1`
    - `JILID 3 (Putra)`
    - `JILID 3 (Putri)`

### 5.2 Monitoring Presensi Harian (`MonitoringView.vue`)
- Pada baris filter yang sudah ada (di samping tanggal dan jilid), ditambahkan dropdown ringkas filter gender:
  - `[Semua Gender]`, `[Putra (L)]`, `[Putri (P)]`.
- Secara otomatis terpilih sesuai `gender_binaan` guru yang login.

### 5.3 Rekapitulasi Presensi & Cetak Laporan (`RekapMatrixView.vue`)
- **Toolbar Filter:**
  - Menambahkan dropdown ringkas filter gender di samping dropdown Jilid: `Semua Gender`, `Putra (L)`, `Putri (P)`.
- **Cetak Laporan Resmi (PDF / Print):**
  - Sub-judul dokumen otomatis menyesuaikan: `Jilid: JILID 3 (PUTRA)` atau `Jilid: JILID 3 (PUTRI)`.
  - Tanda tangan sebelah kanan otomatis memunculkan nama Ustadz pembina (untuk Putra) atau Ustadzah pembina (untuk Putri).
  - Tanda tangan sebelah kiri tetap Kepala TPQ `( Ny. Shofiyah )`.
  - Kerapian layout 1 lembar A4 landscape tetap terjaga.
- **Export CSV:**
  - Memuat metadata jilid dan gender binaan di bagian header CSV, dan nama wali yang sesuai di bagian tanda tangan bawah CSV.

---

## 6. Rencana Pengujian (Testing & Verification)
1. **Migration Test:** Memastikan kolom `gender_binaan` berhasil ditambahkan tanpa merusak data guru yang sudah ada.
2. **Feature Test `UserController`:**
   - Berhasil menugaskan 2 guru ke 1 jilid yang sama dengan gender binaan berbeda (1 Putra, 1 Putri).
   - Menolak jika ada 2 guru yang mencoba mengambil jilid dan gender binaan yang sama.
3. **Feature Test `RekapMatrix` & `Monitoring`:**
   - Filter `jenis_kelamin=L` hanya mengembalikan santri laki-laki.
   - Filter `jenis_kelamin=P` hanya mengembalikan santri perempuan.
4. **Vite Build & Pint:**
   - `npm run build` sukses 100%.
   - `vendor/bin/pint --format agent` lolos tanpa pelanggaran style.
