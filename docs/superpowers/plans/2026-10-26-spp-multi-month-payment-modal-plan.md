# Rencana Implementasi: Modal Pembayaran SPP Multi-Bulan di Data Tagihan & Tunggakan

**Tanggal:** 26 Oktober 2026  
**Dokumen Spesifikasi:** `docs/superpowers/specs/2026-10-26-spp-multi-month-payment-modal-design.md`  
**Tujuan:** Mengimplementasikan pop-up modal pembayaran SPP multi-bulan langsung di halaman Data Tagihan & Tunggakan (`/spp/tagihan`), memungkinkan admin/bendahara memilih beberapa bulan tagihan menunggak santri via checklist dan melunasinya dalam satu transaksi atomik dengan satu nomor kuitansi gabungan resmi.

---

### Batasan Kritis (Critical Constraints)
- **Perlindungan Data Riil Santri**: DILARANG mengubah, menghapus, atau me-reset data pembayaran/tagihan santri yang sudah ada di database MySQL aktif.
- Seluruh automated feature tests wajib menggunakan SQLite `:memory:` terisolasi melalui trait `RefreshDatabase`.

---

### Task 1: Backend Service Method (`SppBillingService::bayarMultiBulan`)
- [ ] Buka `app/Services/SppBillingService.php` dan tambahkan method `bayarMultiBulan`:
  - Parameter: `int $idSiswa`, `array $tagihanIds`, `string $metode`, `?string $catatan`, `int $idPetugas`.
  - Gunakan `DB::transaction` untuk menjamin eksekusi atomik.
  - Validasi seluruh `$tagihanIds` harus milik `$idSiswa` dan berstatus `belum_lunas` atau `kurang_bayar`.
  - Buat 1 nomor kuitansi unik `KW-YYYYMM-XXXXX` menggunakan kunci pesimis `lockForUpdate()` pada record pembayaran terakhir di bulan berjalan.
  - Untuk setiap tagihan:
    - Hitung sisa kewajiban (`nominal_tagihan - sum valid pembayaran`).
    - Buat record `PembayaranSpp` dengan nomor kuitansi gabungan tersebut.
    - Perbarui status `tagihan_spp` menjadi `lunas`.
  - Kembalikan array hasil terstruktur yang memuat rincian kuitansi gabungan (daftar bulan yang dibayar, total nominal, nama santri, petugas, dan tanggal bayar).

---

### Task 2: Controller & Endpoint API (`POST /api/spp/bayar-multi`)
- [ ] Buka `app/Http/Controllers/Api/SppController.php` dan tambahkan method `bayarMulti(Request $request)`:
  - Validasi request:
    - `id_siswa`: `required|exists:siswa,id`
    - `tagihan_ids`: `required|array|min:1`
    - `tagihan_ids.*`: `required|exists:tagihan_spp,id`
    - `metode_pembayaran`: `required|string|in:Tunai,Transfer`
    - `catatan`: `nullable|string|max:255`
  - Ambil `$petugasId = $request->user()->id`.
  - Panggil `$this->billingService->bayarMultiBulan(...)`.
  - Kembalikan response JSON dengan status 200 jika sukses atau 422 jika gagal.
- [ ] Daftarkan rute di `routes/api.php` pada prefix `spp` di grup middleware `role:admin,bendahara`:
  - `Route::post('/bayar-multi', [SppController::class, 'bayarMulti']);`

---

### Task 3: Automated Feature Testing (`SppMultiMonthPaymentTest.php`)
- [ ] Buat file test `tests/Feature/SppMultiMonthPaymentTest.php` dengan SQLite in-memory:
  - Uji admin/bendahara berhasil membayar 2-3 bulan tagihan sekaligus dalam 1 request.
  - Verifikasi bahwa seluruh record pembayaran menggunakan 1 nomor kuitansi yang identik.
  - Verifikasi status seluruh tagihan yang dibayar berubah menjadi `lunas`.
  - Uji penolakan jika tagihan yang dikirim milik santri lain (validasi kepemilikan).
  - Uji penolakan akses untuk role selain admin/bendahara (misal `guru` atau `kepala`).
- [ ] Jalankan test dengan `php artisan test --filter=SppMultiMonthPaymentTest`.

---

### Task 4: Frontend UI Pop-up Modal di `TagihanSppView.vue`
- [ ] Buka `resources/js/views/spp/TagihanSppView.vue`:
  - Ubah tombol "Bayar" di Tab Rekap Santri Menunggak: `@click="openBayarModal(s)"`.
  - Ubah tombol "Bayar" di Tab Semua Tagihan agar memicu modal bayar santri terkait.
  - Tambahkan reactive state modal:
    - `showBayarModal`, `selectedBayarSantri`, `selectedTagihanIds`, `bayarMetode`, `bayarCatatan`, `isSubmittingBayar`, `bayarSuccessResult`.
  - Buat template Pop-up Modal Pembayaran SPP:
    - **Header**: Nama santri, NISN, Jilid/Kelas, badge durasi nunggak.
    - **Checklist Bulan**: Tabel/daftar checkbox bulan-bulan yang belum lunas dengan tombol cepat *"Pilih Semua"* / *"Kosongkan"*.
    - **Live Counter**: Menampilkan realtime *"Terpilih: X Bulan &bull; Total Bayar: Rp XXX.XXX"*.
    - **Form**: Pilihan Metode Bayar (*Tunai / Transfer*) dan input Catatan.
    - **Tombol Proses**: Tombol bayar dengan status loading spinner.
  - Buat Tampilan Sukses / Kuitansi di dalam Modal:
    - Ringkasan pembayaran berhasil dengan nomor kuitansi.
    - Tombol *"Cetak Kuitansi"*.
    - Tombol *"Kirim Bukti via WhatsApp"*.
    - Tombol *"Tutup & Selesai"* yang me-refresh tabel data tunggakan secara otomatis.

---

### Task 5: Formatting, Build & Verifikasi Akhir
- [ ] Jalankan Laravel Pint: `php vendor/bin/pint --format agent`.
- [ ] Jalankan seluruh test suite: `php artisan test`.
- [ ] Kompilasi frontend bundle: `npm run build`.
- [ ] Verifikasi visual alur kerja pembayaran multi-bulan menggunakan browser.
