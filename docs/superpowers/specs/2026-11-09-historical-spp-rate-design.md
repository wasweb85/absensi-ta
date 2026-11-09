# Desain Logika Tarif SPP Historis & Alokasi Multi-Bulan Dinamis

- **Tanggal:** 9 November 2026
- **Status:** Approved
- **Topik:** Penentuan Tarif SPP Otomatis Berdasarkan Periode Kalender (Juni 2026 ke Bawah Rp 10.000, Juli 2026 ke Atas Rp 15.000)

---

## 1. Latar Belakang & Masalah
Di TPQ Nurul Mun'im, tarif SPP sebesar **Rp 15.000/bulan** baru diberlakukan mulai **Juli 2026** (Tahun Ajaran 2026/2027). Untuk periode **Juni 2026 ke bawah** (termasuk tahun 2025), tarif SPP yang berlaku adalah **Rp 10.000/bulan** dan seragam untuk seluruh santri.

Sebelumnya, sistem menyamaratakan seluruh pembuatan tagihan dan alokasi pembayaran multi-bulan menggunakan tarif tunggal Rp 15.000. Akibatnya, ketika wali santri melunasi tunggakan periode lama (misal: Juli 2025 s/d Juni 2026 sebesar Rp 120.000 untuk 12 bulan), sistem memotong Rp 15.000 per bulan sehingga hanya teralokasi 8 bulan dan nominal per bulan pada kwitansi tertulis Rp 15.000.

---

## 2. Tujuan Solusi
1. **Tanpa Menambah Tombol / Menu Baru:** Mempertahankan antarmuka form input pembayaran yang sudah ada dengan menyempurnakan logika perhitungan di balik layar.
2. **Deteksi Tarif Otomatis Berdasarkan Periode:**
   - **Periode $\le$ Juni 2026 (termasuk tahun 2025 ke bawah):** Otomatis Rp 10.000 / bulan.
   - **Periode $\ge$ Juli 2026:** Otomatis Rp 15.000 / bulan.
3. **Alokasi Multi-Bulan Lintas Periode:** Mendukung input pembayaran sekaligus (misal Rp 120.000 mulai Juli 2025 otomatis melunasi 12 bulan s/d Juni 2026 @ Rp 10.000/bulan, serta mendukung alokasi yang menyeberang ke Juli 2026 @ Rp 15.000/bulan).
4. **Rincian Kwitansi Akurat:** Setiap bulan pada kwitansi dan database tercatat sesuai tarif zamannya (Rp 10.000 untuk $\le$ Juni 2026, Rp 15.000 untuk $\ge$ Juli 2026).

---

## 3. Spesifikasi Teknis

### A. Backend Service (`app/Services/SppBillingService.php`)
1. **Method `getTarifSppBulan(int $bulan, int $tahun, ?float $nominalKelas = null): float`:**
   - Mengembalikan `10000.0` apabila `$tahun < 2026 || ($tahun === 2026 && $bulan <= 6)`.
   - Mengembalikan tarif kelas (atau default `15000.0`) apabila `$tahun > 2026 || ($tahun === 2026 && $bulan >= 7)`.
2. **Method `ensureTagihan(int $idSiswa, int $bulan, int $tahun)`:**
   - Saat membuat tagihan baru, menggunakan `getTarifSppBulan($bulan, $tahun)`.
   - Jika tagihan periode $\le$ Juni 2026 sudah terlanjur ada di database dengan status belum lunas namun masih bernominal lama (15.000), sistem otomatis menormalisasi `nominal_tagihan` menjadi `10000.0`.
3. **Method `bayarCerdasMultiBulan(...)`:**
   - Dalam perulangan alokasi bulan demi bulan, setiap bulan memanggil `ensureTagihan` yang sudah menerapkan tarif dinamis, sehingga pemotongan saldo pembayaran berjalan akurat per bulan.

### B. Backend Controller (`app/Http/Controllers/Api/SppController.php`)
- Pada `inputCepat`, pembuatan tagihan massal satu kelas (`ensureTagihan` untuk seluruh siswa aktif di kelas) hanya dijalankan jika periode yang dipilih adalah periode aktif ($\ge$ Juli 2026). Jika periode yang dipilih adalah periode historis ($\le$ Juni 2026), tagihan hanya dibuat untuk santri yang sedang diinput pembayarannya agar santri lain tidak terkena tagihan lama secara tidak sengaja.

### C. Frontend (`resources/js/views/spp/InputCepatView.vue`)
1. **Fungsi `getTarifBulan(bulan, tahun, nominalKelasStandar)`:**
   - Mengembalikan `10000` untuk periode $\le$ Juni 2026 dan `15000` (atau tarif kelas) untuk periode $\ge$ Juli 2026.
2. **Default Nominal Input & Simulasi Pratinjau (`getAlokasiMultiBulanLabel`):**
   - Saat filter bulan/tahun berada di $\le$ Juni 2026, kolom nominal default santri otomatis berisi `10000`.
   - Saat admin mengetik nominal multi-bulan (misal `120000` pada Juli 2025), teks pratinjau menghitung alokasi bulan demi bulan sesuai tarif masing-masing bulan (`✨ Otomatis 12 Bulan (Juli 2025 – Juni 2026)`).

---

## 4. Rencana Pengujian
1. **Simulasi 12 Bulan Historis:** Input Rp 120.000 mulai Juli 2025 menghasilkan tepat 12 bulan lunas (Juli 2025 – Juni 2026) dengan rincian Rp 10.000/bulan.
2. **Simulasi Lintas Tarif:** Input Rp 150.000 mulai Januari 2026 menghasilkan 12 bulan lunas (Januari 2026 – Juni 2026 @ Rp 10.000 dan Juli 2026 – Desember 2026 @ Rp 15.000).
3. **Regresi Penuh:** Seluruh test suite yang ada tetap lulus 100%.

