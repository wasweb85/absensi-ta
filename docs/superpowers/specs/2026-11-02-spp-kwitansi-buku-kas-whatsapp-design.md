# Desain Spesifikasi: Audit & Penyempurnaan Kwitansi, Cetak Buku Kas, dan WhatsApp Bukti Pembayaran SPP

**Tanggal:** 2026-11-02  
**Status:** Draf untuk Disetujui  
**Aplikasi:** E-Absensi & SPP TPQ Nurul Mun'im  

---

## 1. Latar Belakang & Tujuan

Berdasarkan audit alur pembayaran SPP:
1. Pesan WhatsApp bukti pembayaran ke wali santri saat ini masih kaku menyerupai struk minimarket tanpa salam, sapaan hormat, dan doa keberkahan.
2. Tombol "Cetak" di header riwayat membingungkan dan saat dicetak mengeluarkan seluruh tabel halaman web (termasuk kolom aksi dan terpotong pagination), padahal fungsinya adalah **Buku Kas Penerimaan SPP**.
3. Format cetak fisik kuitansi santri membutuhkan perapian agar proporsional (format slip setengah kuarto / A5 landscape) dan memiliki tanda keabsahan resmi.

Tujuan spesifikasi ini adalah menyempurnakan ketiga aspek tersebut menjadi lebih profesional, meyakinkan, dan sesuai kaidah administrasi lembaga pendidikan Islam TPQ Nurul Mun'im.

---

## 2. Arsitektur & Rincian Desain

### A. Format Pesan WhatsApp Bukti Pembayaran Resmi

Pesan WhatsApp bukti pembayaran akan distandarisasi di seluruh view yang memiliki fitur kirim kuitansi (`InputCepatView.vue`, `RiwayatSppView.vue`, `TagihanSppView.vue`, `TransaksiSppView.vue`).

**Struktur Teks:**
```text
*BUKTI PEMBAYARAN RESMI SPP*
*TPQ NURUL MUN'IM PAITON*
━━━━━━━━━━━━━━━━━━━━━━━

_Assalamu’alaikum Warahmatullahi Wabarakatuh_

Yth. Bapak/Ibu Wali dari ananda:
👤 *{nama_santri}* (NISN: {nisn})
📚 Kelas / Jilid: *{nama_kelas}*

Alhamdulillah, pembayaran iuran SPP ananda telah kami terima dan tercatat sah pada buku kas administrasi TPQ Nurul Mun'im dengan rincian:

🧾 *No. Kuitansi* : {nomor_kuitansi}
🗓 *Tanggal Bayar*: {tanggal_bayar_lengkap}
📌 *Keperluan*    : {pembayaran_untuk}
💳 *Metode Bayar* : {metode_pembayaran}
💰 *Jumlah Bayar* : *Rp {jumlah_bayar}*
✅ *Status*       : *LUNAS (SAH)*

━━━━━━━━━━━━━━━━━━━━━━━
_Jazakumullahu khairan katsiran_ atas kepedulian Bapak/Ibu dalam mendukung pendidikan Al-Qur'an ananda. Semoga Allah SWT membalas dengan rezeki yang berkah berlimpah, serta menjadikan ananda generasi sholeh/sholehah yang mencintai Al-Qur'an. Aamiin ya Rabbal 'Alamin.

_Wassalamu’alaikum Warahmatullahi Wabarakatuh_

Hormat kami,
*Bendahara TPQ Nurul Mun'im*
({nama_bendahara})
_Karanganyar, Paiton, Probolinggo_
```

---

### B. Cetak Kuitansi Resmi Santri (Slip Kuitansi)

1. **Ukuran Dokumen:** Dibatasi dalam format slip proporsional (*A5 Landscape / Setengah Kuarto*) via CSS `@media print`.
2. **Kop Surat:** Kop resmi Yayasan Nurul Jadid & TPQ Nurul Mun'im.
3. **Cap Keabsahan:** Menampilkan watermark atau badge pengesahan *"LUNAS & TERVERIFIKASI SISTEM"*.
4. **Pengesahan Tanda Tangan:** Dua kolom sejajar (Penyetor / Wali Santri di kiri, Bendahara TPQ di kanan).

---

### C. Cetak Buku Kas (Laporan Kas Penerimaan SPP) di Halaman Riwayat

1. **Penamaan Tombol:** Mengubah label tombol di header riwayat dari *"Cetak Laporan"* / *"Cetak"* menjadi **"Cetak Buku Kas"**.
2. **Penarikan Seluruh Data Terfilter (*Fetch All Data*):**
   - Backend `SppController@riwayatPembayaran` mendukung parameter `semua=1` atau `per_page=all`.
   - Tombol "Cetak Buku Kas" mengambil seluruh riwayat transaksi sesuai filter yang sedang aktif (tidak dibatasi 30 transaksi halaman pagination aktif).
3. **Layout Cetak Kertas A4 Portrait:**
   - Kop Surat Resmi TPQ Nurul Mun'im.
   - Judul Dokumen: **BUKU KAS PENERIMAAN SPP SANTRI**.
   - Ringkasan Filter: Rentang Tanggal, Jilid/Kelas, Status.
   - Tabel Buku Kas Khusus Cetak:
     - Nomor Urut (1, 2, 3...)
     - No. Kuitansi
     - Tanggal Bayar
     - Nama Santri & NISN
     - Jilid / Kelas
     - Periode Tagihan
     - Nominal (Rp)
     - Metode
   - Kolom yang **disembunyikan saat cetak**: Kolom "Aksi", Kolom tombol batalkan transaksi.
   - Baris Total: Jumlah akumulasi kas masuk pada periode yang terfilter.
   - Kolom Tanda Tangan Resmi:
     - Kiri: Mengetahui, Kepala TPQ (Ny. Shofiyah)
     - Kanan: Paiton, [Tanggal], Bendahara TPQ ([Nama Bendahara]).

---

## 3. Rencana Pengujian

1. **Unit/Feature Test:** Memastikan endpoint `/api/spp/riwayat` dapat mengembalikan seluruh data jika diminta `semua=1`.
2. **Format Pesan WA:** Menguji pembentukan tautan `https://wa.me/...` dengan teks terenkripsi UTF-8, memastikan emoji dan line breaks rapi.
3. **Cetak Buku Kas:** Menguji pratinjau cetak browser (`window.print()`) bahwa tabel buku kas rapi, tidak terpotong ke samping, dan kolom aksi tidak muncul.
4. **Cetak Kuitansi:** Memastikan kuitansi saat dicetak tidak menyisakan halaman kosong berlebih.

