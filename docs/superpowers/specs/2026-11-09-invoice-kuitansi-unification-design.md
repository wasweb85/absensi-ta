# Desain Penyelarasan Kwitansi & Invoice SPP (Mobile-First Hybrid Model)

- **Tanggal:** 9 November 2026
- **Status:** Approved
- **Topik:** Penyelarasan Data Transaksi & Desain Responsif Mobile Kwitansi SPP

---

## 1. Latar Belakang & Masalah
Saat ini terdapat inkonsistensi antara 3 tampilan bukti pembayaran SPP di sistem TPQ Nurul Mun'im:
1. **Modal Kwitansi Web Admin (Gambar 1):** Menggunakan desain blangko cetak kertas formal. Jika santri melunasi transaksi multi-bulan (contoh: 12 bulan sekaligus), modal di riwayat hanya menampilkan 1 baris pecahan yang diklik (contoh: `KW-202611-00104-12`, Rp 15.000, Juni 2027).
2. **Modal Kirim WhatsApp PNG (Gambar 2):** Menghasilkan gambar blangko kertas via Canvas, tetapi juga hanya membaca data baris pecahan yang diklik (Rp 15.000).
3. **Halaman Publik Web dari Tautan WA (Gambar 3):** Menampilkan desain kartu digital modern dengan akumulasi penuh 12 bulan (`KW-202611-00104`, Rp 180.000, Juli 2026 s/d Juni 2027).

Ketidaksinkronan ini membingungkan administrator dan wali santri karena nominal dan periode yang tertulis di modal admin berbeda dengan ringkasan yang tampil di tautan invoice wali santri.

---

## 2. Tujuan Pengembangan
1. **Penyelarasan Data (Data Synchronization):** Semua tampilan (Modal Admin, Gambar PNG WA, dan Halaman Tautan Web) menampilkan data yang sama persis: nomor kuitansi induk (`KW-202611-00104`), total nominal pelunasan (`Rp 180.000`), dan periode lengkap yang ringkas.
2. **Desain Hybrid Profesional & Mobile-First:** Halaman web publik (`/invoice/{token}`) dioptimalkan untuk kenyamanan layar smartphone wali santri (360px – 430px), menyajikan ringkasan digital yang bersih sekaligus pratinjau dokumen kuitansi resmi ber-KOP dan berstempel sah yang siap cetak/unduh.
3. **Penyajian Periode Cerdas (Smart Range Display):** Jika pembayaran mencakup banyak bulan (misal 12 bulan), teks periode dirangkum secara elegan: `Juli 2026 – Juni 2027 (12 Bulan)` dengan opsi daftar rincian bulan agar tidak memenuhi layar ponsel.

---

## 3. Arsitektur & Spesifikasi Rinci

### A. Lapisan Penyelarasan Data Transaksi
* **Deteksi Transaksi Multi-Bulan di Frontend:**
  * Pada `RiwayatSppView.vue`, `InputCepatView.vue`, dan `TagihanSppView.vue`: saat admin mengklik kuitansi transaksi yang memiliki format multi-bulan (`KW-YYYYMM-XXXXX-Y` atau kuitansi induk `KW-YYYYMM-XXXXX`), sistem mencari seluruh kuitansi saudara (`sibling payments`) yang memiliki nomor induk yang sama dan tanggal pembayaran yang sama.
  * Sistem mengumpulkan:
    - `nomor_kuitansi`: Nomor kuitansi induk (contoh `KW-202611-00104`).
    - `total_bayar` / `jumlah_bayar`: Akumulasi total nominal seluruh bulan (contoh `Rp 180.000`).
    - `periode`: Format ringkas rentang bulan, contoh `Juli 2026 – Juni 2027 (12 Bulan)`.
* **Dukungan Backend (`app/Http/Controllers/PublicInvoiceController.php`):**
  * Mempertahankan pengurutan kronologis kalender tagihan `(tahun * 100) + bulan`.
  * Menghitung format ringkas `periodeRingkas`: jika rentang bulan berurutan $\ge 3$ bulan, otomatis dibentuk format `[Bulan Awal Tahun Awal] – [Bulan Akhir Tahun Akhir] (X Bulan)`.

### B. Gambar Canvas Kuitansi WhatsApp (`resources/js/utils/kuitansiCanvas.js`)
* Memperbarui perenderan Canvas:
  - Baris `No. Kuitansi` menampilkan nomor kuitansi induk bersih tanpa akhiran indeks pecahan jika merupakan transaksi multi-bulan.
  - Baris `Pembayaran Periode` menampilkan `Juli 2026 – Juni 2027 (12 Bulan)`.
  - Baris `Jumlah Disetor` menampilkan akumulasi total yang sah (`Rp 180.000`).
* Mempertahankan KOP resmi Yayasan Nurul Jadid & TPQ Nurul Mun'im, stempel hijau `LUNAS-SAH`, dan tanda tangan bendahara.

### C. Halaman Publik Web Mobile-First (`resources/views/public_invoice.blade.php`)
* **Viewport & Typography Mobile:**
  - Desain kartu responsif dengan lebar maksimum 480px di desktop dan lebar penuh fleksibel di smartphone dengan margin aman (16px).
  - Status banner hijau `PEMBAYARAN LUNAS (SAH)` yang tegas di bagian atas kartu.
  - Periode SPP menampilkan teks utama ringkas: **Juli 2026 – Juni 2027 (12 Bulan)**.
  - Tambahan elemen interaktif *Daftar Rincian Bulan* (accordion/toggle sederhana) yang memungkinkan wali santri melihat rincian ke-12 bulan tanpa membuat halaman terasa panjang.
* **Pratinjau Kuitansi Resmi Tersemat:**
  - Jika gambar kuitansi Cloudinary tersedia, ditampilkan dalam wadah gambar dengan bayangan lembut dan tombol zoom/unduh.
  - Tombol aksi di bagian bawah dirancang ramah sentuhan jempol (*thumb-friendly*):
    1. Tombol Utama (Hijau): **Cetak / Simpan PDF** (atau **Unduh Gambar Asli**).
    2. Tombol Kedua (Outline): **Hubungi Bendahara TPQ (WhatsApp)**.

---

## 4. Rencana Pengujian
1. **Unit & Feature Test:**
   - Memastikan pembayaran tunggal (1 bulan) tetap menampilkan 1 bulan dan nominal tunggal.
   - Memastikan pembayaran multi-bulan (12 bulan) menampilkan nomor kuitansi induk, nominal total, dan urutan bulan kronologis yang ringkas.
2. **Uji Antarmuka Mobile:**
   - Memastikan tidak ada teks tumpang tindih (*overflow*) di viewport layar HP (360px – 430px).
   - Memastikan fungsi *print to PDF* dan tombol aksi WhatsApp berfungsi dengan baik.

---

## 5. Kriteria Keberhasilan (Acceptance Criteria)
1. Data pada Modal Admin (Gambar 1), Modal Kirim WA (Gambar 2), dan Tautan Publik Web (Gambar 3) sinkron:
   - Nomor kuitansi: `KW-202611-00104`
   - Total bayar: `Rp 180.000`
   - Periode: `Juli 2026 – Juni 2027 (12 Bulan)`
2. Tampilan di smartphone nyaman, tidak ada teks terpotong, dan terasa resmi serta profesional.
3. Seluruh test suite aplikasi lulus 100%.
