# Spesifikasi Desain: WhatsApp Chatbot Layanan Informasi Wali Santri (SPP, Presensi, Agenda)

- **Tanggal Dokumen:** 19 Oktober 2026
- **Status:** Approved
- **Tujuan Proyek:** Pembaruan inovasi Tugas Akhir / Skripsi berupa layanan informasi otomatis dua arah (*Two-Way Chatbot*) berbasis WhatsApp Gateway Fonnte untuk wali santri TPQ.

---

## 1. Latar Belakang & Sasaran Inovasi Skripsi

### 1.1 Masalah Saat Ini
Sebelumnya, sistem informasi TPQ hanya bersifat satu arah (*One-Way Broadcast Notification*), di mana pengurus mengirim tagihan atau pengumuman melalui blast WhatsApp. Wali santri yang ingin menanyakan besaran tunggakan SPP, riwayat pembayaran, atau status kehadiran anak harus menghubungi nomor pribadi pengajar/ustadzah satu per satu, yang membebani staf administrasi dan berpotensi lambat ditanggapi.

### 1.2 Solusi Inovasi
Membangun **Chatbot WhatsApp Interaktif Realtime**:
Wali santri cukup mengirim pesan santai melalui aplikasi WhatsApp mereka (misalnya: *"Berapa tunggakan anak saya?"* atau *"Gimana kehadiran Ahmad hari ini?"*), sistem secara otomatis mengidentifikasi identitas santri berdasarkan nomor pengirim, mengambil data aktual dari basis data Laravel MySQL, dan membalas dalam hitungan detik dengan format yang terstruktur, santun, dan islami.

---

## 2. Arsitektur Sistem & Komponen

```
                  ┌───────────────────────────────┐
                  │ Wali Santri (Aplikasi WhatsApp)│
                  └──────────────┬────────────────┘
                                 │ Pesan WhatsApp
                                 ▼
                  ┌───────────────────────────────┐
                  │    Fonnte WhatsApp Gateway    │
                  └──────────────┬────────────────┘
                                 │ HTTP POST Webhook Payload
                                 ▼
         ┌─────────────────────────────────────────────────┐
         │ Laravel Backend API: POST /api/webhook/whatsapp │
         └───────────────────────┬─────────────────────────┘
                                 │
                                 ▼
                     ┌───────────────────────┐
                     │   ChatbotController   │
                     └───────────┬───────────┘
                                 │
                                 ▼
                     ┌───────────────────────┐
                     │    ChatbotService     │
                     └───────────┬───────────┘
         ┌───────────────────────┼────────────────────────┐
         ▼                       ▼                        ▼
┌──────────────────┐   ┌───────────────────┐    ┌───────────────────┐
│ Identifikasi     │   │ Smart Keyword     │    │ Cache Manager     │
│ Santri (no_hp)   │   │ Intent Recognizer │    │ (Sesi Multi-Anak) │
└────────┬─────────┘   └─────────┬─────────┘    └─────────┬─────────┘
         │                       │                        │
         └───────────────────────┼────────────────────────┘
                                 │
                                 ▼
             ┌───────────────────────────────────────┐
             │ Query Data Database (Eloquent Models) │
             │ - Siswa & Kelas                       │
             │ - TagihanSpp & PembayaranSpp          │
             │ - Absensi Santri                      │
             │ - Agenda & HariLibur                  │
             └───────────────────┬───────────────────┘
                                 │
                                 ▼
             ┌───────────────────────────────────────┐
             │ 1. Format Balasan Teks WhatsApp       │
             │ 2. Catat Percakapan ke `chatbot_logs` │
             │ 3. Kirim Respon ke Fonnte / Pengirim  │
             └───────────────────────────────────────┘
```

---

## 3. Skema Basis Data (Database Schema)

### 3.1 Tabel Baru: `chatbot_logs`
Tabel ini berfungsi sebagai rekam jejak (*audit trail*) setiap percakapan masuk dan respon bot, yang dapat dipantau oleh pengurus melalui Web Admin.

```php
Schema::create('chatbot_logs', function (Blueprint $table) {
    $table->id();
    $table->string('no_pengirim', 25)->index(); // Format 628xxx
    $table->text('pesan_masuk');
    $table->string('intent_terdeteksi', 50)->default('unknown')->index(); // spp, absensi, agenda, bantuan, unknown
    $table->foreignId('id_siswa')->nullable()->constrained('siswa')->nullOnDelete();
    $table->text('pesan_keluar');
    $table->enum('status', ['sukses', 'gagal'])->default('sukses');
    $table->text('keterangan_error')->nullable();
    $table->timestamps();
});
```

### 3.2 Model Eloquent: `app/Models/ChatbotLog.php`
- Relasi: `belongsTo(Siswa::class, 'id_siswa')`.
- Relasi di Model `Siswa`: `hasMany(ChatbotLog::class, 'id_siswa')`.

---

## 4. Spesifikasi Endpoint API & Webhook

| Method | Endpoint | Hak Akses | Deskripsi |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/webhook/whatsapp` | Publik (Fonnte) | Menerima payload webhook pesan masuk dari Fonnte. |
| `POST` | `/api/chatbot/simulate` | `auth:sanctum` (`admin`, `kepala`) | Menjalankan simulasi chat interaktif di Web Admin. |
| `GET` | `/api/chatbot/logs` | `auth:sanctum` (`admin`, `kepala`) | Mengambil riwayat log interaksi chatbot (pagination & filter). |
| `GET` | `/api/chatbot/stats` | `auth:sanctum` (`admin`, `kepala`) | Statistik ringkas efektivitas chatbot (hari ini & bulan ini). |

### 4.1 Format Payload Masuk dari Fonnte
Fonnte mengirimkan data via `POST` dengan format standar:
```json
{
  "sender": "6281234567890",
  "message": "Assalamu'alaikum, berapa tunggakan anak saya?"
}
```

---

## 5. Mesin Klasifikasi Intent (Smart Keyword & Tokenizer)

Sistem menggunakan normalisasi teks (lowercase, pembersihan karakter non-alfanumerik) dan token matching untuk memetakan pesan ke dalam 5 kategori intent:

### 5.1 Intent `spp` (Cek Tagihan & Tunggakan)
- **Kata Kunci Pemicu:** `tunggakan`, `spp`, `tagihan`, `biaya`, `bayar`, `lunas`, `utang`, `belum bayar`, `kurang bayar`.
- **Query Data:**
  - Mencari seluruh record `TagihanSpp` milik santri dengan status `belum_lunas` atau `kurang_bayar`.
- **Format Respon Jika Ada Tunggakan:**
  - Menyebutkan nama lengkap santri & kelas/jilid.
  - Rincian bulan tagihan dan nominal masing-masing.
  - Total nominal tunggakan yang harus diselesaikan.
  - Informasi rekening bank resmi TPQ (diambil dari pengaturan sistem).
- **Format Respon Jika Tidak Ada Tunggakan (Lunas):**
  - Pesan apresiasi islami bahwa seluruh kewajiban SPP ananda telah lunas tercatat di sistem.

### 5.2 Intent `absensi` (Cek Presensi & Kehadiran)
- **Kata Kunci Pemicu:** `presensi`, `absensi`, `kehadiran`, `absen`, `masuk`, `hadir`, `bolos`, `izin`, `sakit`.
- **Query Data:**
  - Status presensi hari ini pada tanggal `now()->toDateString()`.
  - Rekapitulasi absensi bulan berjalan (total hadir, terlambat, izin, sakit, alpa, dan persentase kehadiran).
- **Format Respon:**
  - Rincian kehadiran hari ini (status dan jam scan).
  - Ringkasan statistik bulanan santri secara informatif.

### 5.3 Intent `agenda` (Info Agenda & Kalender Libur)
- **Kata Kunci Pemicu:** `libur`, `agenda`, `kegiatan`, `acara`, `jadwal`, `kapan libur`.
- **Query Data:**
  - Mengambil data hari libur dari tabel `hari_libur` (tanggal selesai >= hari ini).
  - Mengambil kegiatan terdekat dari tabel `agenda`.
- **Format Respon:**
  - Daftar tanggal libur dan agenda TPQ yang akan datang.

### 5.4 Intent `bantuan` / Sapaan Umum
- **Kata Kunci Pemicu:** `halo`, `hai`, `assalamualaikum`, `menu`, `bantuan`, `info`, `bisa apa saja`, `bantuan`.
- **Format Respon:**
  - Ucapan salam santun islami.
  - Konfirmasi data santri yang terhubung dengan nomor WhatsApp tersebut.
  - Petunjuk singkat pertanyaan yang bisa diajukan oleh wali santri.

---

## 6. Penanganan Skenario Khusus

### 6.1 Skenario Multi-Santri (Satu Nomor Wali Memiliki Lebih dari 1 Anak)
- Jika satu nomor telepon terhubung ke 2 santri atau lebih di tabel `siswa`:
  1. Cek apakah ada sesi pemilihan anak aktif di Cache: `Cache::get("wa_session_{$senderPhone}")`.
  2. Jika belum ada atau pengguna mengetik angka pilihan (`1` atau `2`):
     - Simpan pilihan anak aktif ke sesi selama 15 menit (`Cache::put("wa_active_student_{$senderPhone}", $siswaId, now()->addMinutes(15))`).
  3. Jika sesi baru dimulai, tampilkan daftar anak:
     ```text
     Nomor WhatsApp Anda terhubung dengan 2 santri TPQ:
     1. Ahmad Fauzi (Jilid 1)
     2. Siti Aisyah (Jilid 3)
     
     Silakan ketik angka *1* atau *2* untuk memilih data ananda yang ingin dicek.
     ```

### 6.2 Skenario Nomor Belum Terdaftar (Unregistered Sender)
- Jika nomor pengirim tidak ditemukan di kolom `no_hp_ortu` tabel `siswa`:
  1. Cek apakah pesan diawali format `NISN [angka]` (misal: `NISN 1001`).
  2. Jika cocok dengan NISN yang valid, tawarkan opsi untuk menautkan nomor atau tampilkan data santri tersebut secara satu kali verifikasi.
  3. Jika bukan format NISN, berikan panduan santun:
     ```text
     Mohon maaf, nomor WhatsApp Anda (*081234xxx*) belum terdaftar di basis data santri TPQ.
     
     Jika Anda adalah wali santri, silakan ketik:
     *NISN [Nomor Induk]*
     Contoh: *NISN 1001*
     
     Atau hubungi bagian administrasi TPQ untuk pembaruan nomor telepon.
     ```

---

## 7. Desain Antarmuka Web Admin (Frontend Vue 3)

### 7.1 Halaman Baru: `resources/js/views/chatbot/ChatbotView.vue`
Halaman ini memiliki 3 Tab utama:
1. **Tab 1: Log Percakapan Realtime**
   - KPI Cards: Total Chat Hari Ini, Pertanyaan SPP, Pertanyaan Presensi, Tingkat Keberhasilan.
   - Tabel interaktif: Waktu, Nomor Pengirim, Nama Santri, Pesan Masuk, Badge Intent, Balasan Bot, Status Pengiriman.
   - Filter Tanggal, Filter Intent, Search Box.
2. **Tab 2: Simulator Chatbot Interaktif (Live Demo)**
   - Kotak chat interaktif menyerupai aplikasi pesan modern.
   - Penguji/Admin dapat memilih santri sampel atau memasukkan nomor HP santri, lalu mengetik pesan apa pun.
   - Bot membalas seketika di layar simulasi (memanggil `POST /api/chatbot/simulate`). Sangat aman untuk demonstrasi sidang tanpa kendala jaringan eksternal.
3. **Tab 3: Pengaturan & Panduan Webhook**
   - URL Webhook siap salin untuk dipasang di dashboard Fonnte.
   - Panduan konfigurasi webhook Fonnte (Event: incoming message, Method: POST).

---

## 8. Strategi Pengujian (Automated Testing)

File pengujian: `tests/Feature/ChatbotWebhookTest.php`
- `test_webhook_responds_spp_tunggakan_correctly()`
- `test_webhook_responds_spp_lunas_when_no_arrears()`
- `test_webhook_responds_attendance_today_and_monthly_recap()`
- `test_webhook_handles_unregistered_phone_gracefully()`
- `test_webhook_handles_multi_student_selection()`
- `test_chatbot_logs_recorded_in_database()`
- `test_chatbot_simulator_endpoint_works()`

---

## 9. Kesimpulan & Nilai Tambah Skripsi
Pembaruan ini mentransformasi sistem informasi TPQ dari sekadar web aplikasi pencatat internal menjadi **ekosistem layanan digital publik terpadu** yang mempermudah interaksi antara wali santri dan pihak lembaga secara otomatis, transparan, dan realtime.
