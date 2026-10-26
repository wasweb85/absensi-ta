# Spesifikasi Desain: Modal Pembayaran SPP Multi-Bulan di Data Tagihan & Tunggakan

- **Tanggal Pembuatan**: 2026-10-26
- **Status**: Disetujui (Approved by User)
- **Modul**: Keuangan & Pembayaran SPP
- **Target Role Pengguna**: Admin & Bendahara TPQ

---

## 1. Latar Belakang & Masalah

Pada alur operasional sebelumnya:
1. Halaman **Input Cepat** (`/spp/input-cepat`) dirancang berbasis satu bulan tertentu (`selectedBulan` dan `selectedTahun`). Alur ini efisien untuk pencatatan massal seluruh santri di satu kelas pada awal bulan, namun tidak fleksibel saat menghadapi santri yang ingin **melunasi tunggakan beberapa bulan sekaligus** (misal 2 s/d 5 bulan berturut-turut).
2. Ketika santri menunggak beberapa bulan ingin membayar, admin/bendahara terpaksa harus mengganti dropdown bulan satu per satu, mencentang santri, memproses pembayaran, lalu mengulang proses tersebut untuk bulan berikutnya. Hal ini memakan waktu yang lama dan rentan keliru.
3. Dari halaman **Data Tagihan & Tunggakan** (`/spp/tagihan`), tombol "Bayar" sebelumnya mengarahkan (redirect) ke halaman Input Cepat. Padahal, bendahara lebih sering memeriksa santri menunggak langsung di tabel rekap tunggakan dan ingin segera menyelesaikan pembayaran di tempat.

Tujuan spesifikasi ini adalah menghadirkan **Pop-up Modal Pembayaran SPP Multi-Bulan** langsung di halaman **Data Tagihan & Tunggakan** (`/spp/tagihan`). Modal ini memungkinkan admin memilih beberapa bulan sekaligus via checklist, melihat total bayar otomatis, memproses pelunasan dalam 1 klik dengan 1 nomor kuitansi gabungan, serta mencetak bukti kuitansi atau mengirimkannya via WhatsApp.

---

## 2. Batasan Kritis (Critical Constraints)

> [!IMPORTANT]
> **Integritas Data Pembayaran Riil**:
> Data pembayaran dan transaksi santri yang telah diinput pengguna di lingkungan database aktif adalah data **VALID & RIIL**.
> - **DILARANG KERAS** memodifikasi, menghapus, atau me-reset data pembayaran santri di database lokal/aktif saat testing.
> - Seluruh pengujian fitur pembayaran wajib berjalan pada database pengujian terisolasi (SQLite `:memory:` via PHPUnit Feature Test) dengan model factories.

---

## 3. Arsitektur Backend & Database

### 3.1 Skema Data Pembayaran (`pembayaran_spp`)
Tidak diperlukan migrasi tabel baru karena struktur tabel `pembayaran_spp` dan `tagihan_spp` sudah mendukung relasi pembayaran per tagihan.

Ketika santri membayar $N$ bulan sekaligus:
1. Sistem menghasilkan **1 Nomor Kuitansi Unik** dengan format `KW-YYYYMM-XXXXX` menggunakan mekanisme kunci pesimis (`lockForUpdate`) agar aman terhadap akses bersamaan (*concurrency safe*).
2. Dibuat $N$ record di tabel `pembayaran_spp` (satu record per `id_tagihan`), dengan seluruh record memiliki **nilai `nomor_kuitansi` yang sama**.
3. Status setiap record `tagihan_spp` yang dibayar lunas diperbarui menjadi `lunas`.
4. Seluruh operasi dibungkus dalam `DB::transaction(...)` untuk menjamin sifat *atomicity* (semua sukses atau semua di-rollback jika terjadi kegagalan).

### 3.2 Service Layer: `SppBillingService::bayarMultiBulan`

Method baru ditambahkan ke `app/Services/SppBillingService.php`:
```php
/**
 * Memproses pembayaran multi-bulan untuk satu santri sekaligus
 *
 * @param int $idSiswa
 * @param array<int> $tagihanIds Daftar ID tagihan yang dipilih
 * @param string $metode Metode bayar ('Tunai' atau 'Transfer')
 * @param string|null $catatan Keterangan opsional
 * @param int $idPetugas ID user petugas yang mencatat
 * @return array{success: bool, message: string, kuitansi: array}
 */
public function bayarMultiBulan(int $idSiswa, array $tagihanIds, string $metode, ?string $catatan, int $idPetugas): array
```

**Aturan Bisnis di Service:**
- Validasi kepemilikan: Setiap ID di `$tagihanIds` harus benar-benar milik `$idSiswa` dan berstatus `belum_lunas` atau `kurang_bayar`.
- Menghitung total sisa kewajiban dari seluruh tagihan yang dipilih.
- Membuat record pembayaran untuk masing-masing tagihan dengan kuitansi gabungan.
- Mengembalikan payload kuitansi terstruktur yang memuat rincian nama bulan, total nominal, dan data santri.

### 3.3 Controller & API Endpoint

- **Endpoint**: `POST /api/spp/bayar-multi`
- **Middleware**: `role:admin,bendahara`
- **Request Validation**:
  ```php
  $request->validate([
      'id_siswa' => 'required|exists:siswa,id',
      'tagihan_ids' => 'required|array|min:1',
      'tagihan_ids.*' => 'required|exists:tagihan_spp,id',
      'metode_pembayaran' => 'required|string|in:Tunai,Transfer',
      'catatan' => 'nullable|string|max:255',
  ]);
  ```
- **Response Format**:
  ```json
  {
      "success": true,
      "message": "Pembayaran 3 bulan SPP berhasil dicatat. Kuitansi: KW-202610-0005",
      "kuitansi": {
          "nomor_kuitansi": "KW-202610-0005",
          "tanggal_bayar": "26/10/2026 23:00",
          "nama_santri": "Ahmad Fauzi",
          "nisn": "1001",
          "kelas": "JILID 1",
          "daftar_bulan": ["Juli 2026", "Agustus 2026", "September 2026"],
          "total_bayar": 150000,
          "metode": "Tunai",
          "petugas": "Administrator",
          "catatan": "Pelunasan semester ganjil"
      }
  }
  ```

---

## 4. Antarmuka Pengguna & Komponen Frontend

Komponen ditempatkan pada `resources/js/views/spp/TagihanSppView.vue` agar terintegrasi erat dengan tabel rekapitulasi tunggakan.

### 4.1 State & Reaktifitas Modal
- `showBayarModal`: Boolean untuk membuka/menutup modal pembayaran.
- `selectedBayarSantri`: Objek santri yang dipilih dari baris tabel.
- `selectedTagihanIds`: Array `Set`/array ID tagihan yang dicentang.
- `bayarMetode`: Nilai default `'Tunai'` (pilihan: `'Tunai'`, `'Transfer'`).
- `bayarCatatan`: Teks catatan opsional.
- `isSubmittingBayar`: Status loading saat request dikirim.
- `bayarSuccessResult`: Menyimpan objek kuitansi setelah berhasil bayar.

### 4.2 Struktur Modal Pembayaran (Step 1: Input Pembayaran)
1. **Header Modal**:
   - Ikon dompet hijau (`wallet`), judul *"Pembayaran SPP Santri"*, tombol close `(X)`.
2. **Kartu Profil Santri**:
   - Nama lengkap, NISN, Jilid / Kelas, Tarif per bulan.
3. **Daftar Checklist Bulan Tunggakan**:
   - Menampilkan daftar bulan yang belum lunas (diambil dari `s.daftar_bulan`).
   - Setiap baris bulan memiliki checkbox, label bulan & tahun, dan nominal tagihan / sisa.
   - Tombol toggle cepat:
     - `Pilih Semua Bulan` (mencentang semua tagihan).
     - `Kosongkan` (membatalkan semua centang).
4. **Ringkasan Kalkulator Realtime**:
   - Menghitung jumlah bulan terpilih dan total nominal bayar (`Rp XXX.XXX`).
5. **Form Metode & Catatan**:
   - Dropdown metode bayar (*Tunai* / *Transfer*).
   - Input keterangan opsional.
6. **Footer Aksi**:
   - Tombol `Batal`.
   - Tombol utama: `Bayar Sekarang (X Bulan • Rp XXX.XXX)` (nonaktif jika tidak ada bulan yang dicentang).

### 4.3 Struktur Modal Kuitansi (Step 2: Bukti Pembayaran Sukses)
Setelah transaksi sukses:
1. Modal otomatis beralih menampilkan kartu bukti kuitansi resmi.
2. Informasi kuitansi:
   - Nomor Kuitansi (`KW-YYYYMM-XXXXX`).
   - Tanggal & waktu transaksi.
   - Rincian bulan yang dilunasi.
   - Total nominal yang dibayarkan dan metode bayar.
3. Tombol aksi pasca-bayar:
   - **Cetak Kuitansi**: Memicu window print / preview kuitansi.
   - **Kirim WA Kuitansi**: Mengirim teks rincian kuitansi ke nomor WhatsApp orang tua.
   - **Tutup & Selesai**: Menutup modal dan memanggil `loadRekapTunggakan()` serta `loadTagihan()` untuk menyinkronkan data tabel tanpa reload halaman.

---

## 5. Rencana Pengujian (Testing Strategy)

1. **Feature Test (Backend)**:
   - File: `tests/Feature/SppMultiMonthPaymentTest.php`
   - Test Cases:
     - `test_admin_can_pay_multiple_months_in_single_transaction`: Memverifikasi pelunasan 2 bulan sekaligus menghasilkan 1 nomor kuitansi dan status tagihan lunas.
     - `test_cannot_pay_tagihan_belonging_to_another_student`: Memastikan sistem menolak jika ada tagihan yang bukan milik santri tersebut.
     - `test_non_authorized_role_cannot_process_payment`: Memastikan user non-admin/bendahara ditolak (403).
2. **Frontend Validation**:
   - Build Vite (`npm run build`) tanpa error sintaksis.
   - Verifikasi interaksi modal dan checklist di browser.
