# Rencana Implementasi: WhatsApp Chatbot Layanan Informasi Wali Santri (SPP, Presensi, Agenda)

**Tanggal:** 19 Oktober 2026  
**Dokumen Spesifikasi:** `docs/superpowers/specs/2026-10-19-whatsapp-chatbot-wali-santri-design.md`  
**Tujuan:** Mengimplementasikan layanan informasi dua arah otomatis (*Two-Way Chatbot*) via Webhook WhatsApp Fonnte untuk wali santri, dilengkapi mesin pengenal intent cerdas (SPP, Presensi, Agenda), audit trail logging, dan antarmuka Simulator Chatbot interaktif di Web Admin untuk demonstrasi sidang skripsi.

---

### Task 1: Migrasi Database & Model `ChatbotLog`
- [ ] Buat file migrasi `create_chatbot_logs_table`:
  - Kolom: `id`, `no_pengirim`, `pesan_masuk`, `intent_terdeteksi`, `id_siswa` (nullable foreign key ke `siswa`), `pesan_keluar`, `status` (`sukses`/`gagal`), `keterangan_error` (nullable), `timestamps`.
- [ ] Buat model `app/Models/ChatbotLog.php` dengan relasi `belongsTo(Siswa::class, 'id_siswa')`.
- [ ] Tambahkan relasi `hasMany(ChatbotLog::class, 'id_siswa')` pada model `app/Models/Siswa.php`.
- [ ] Jalankan migrasi database dengan `php artisan migrate`.

### Task 2: Core Chatbot Engine Service (`ChatbotService.php`)
- [ ] Buat class service `app/Services/ChatbotService.php` dengan metode:
  - `handleIncomingMessage(string $sender, string $message): array`
  - `detectIntent(string $message): string` (Klasifikasi intent: `spp`, `absensi`, `agenda`, `bantuan`, `unknown` via tokenizer dan regex pattern)
  - `resolveStudent(string $normalizedPhone, string $message): array` (Mendeteksi santri: single match, multi-student session via Laravel Cache, direct NISN fallback, unregistered)
  - `handleSppQuery(Siswa $siswa): string` (Kalkulasi rincian tunggakan `TagihanSpp` belum lunas + total nominal + rekening TPQ, atau pesan apresiasi jika lunas)
  - `handleAbsensiQuery(Siswa $siswa): string` (Kalkulasi kehadiran hari ini + statistik bulanan: hadir, terlambat, izin, sakit, alpa, persentase)
  - `handleAgendaQuery(): string` (Jadwal agenda mendatang dari `Agenda` dan rentang `HariLibur`)
  - `handleHelpQuery(?Siswa $siswa): string` (Pesan panduan ramah islami dan contoh pertanyaan)
  - `logInteraction(string $phone, string $message, string $intent, ?int $siswaId, string $reply, string $status, ?string $error = null): ChatbotLog`

### Task 3: Controller & Endpoint API Webhook
- [ ] Buat controller `app/Http/Controllers/Api/ChatbotController.php`:
  - `webhook(Request $request)`: Menerima payload dari Fonnte (`sender`, `message`), memproses via `ChatbotService`, mencatat log, dan mengembalikan respon JSON format Fonnte.
  - `simulate(Request $request)`: Endpoint simulasi interaktif untuk web admin (menerima `phone` dan `message`, mengembalikan respon bot dan intent tanpa kirim WA riil).
  - `logs(Request $request)`: Mengambil daftar `ChatbotLog` dengan pagination, filter intent, tanggal, dan pencarian.
  - `stats(Request $request)`: Mengambil statistik ringkas chat masuk hari ini, bulan ini, proporsi intent, dan tingkat keberhasilan.
- [ ] Daftarkan rute di `routes/api.php`:
  - Publik: `POST /api/webhook/whatsapp` (dilindungi middleware `throttle:60,1`)
  - Terproteksi: `POST /api/chatbot/simulate`, `GET /api/chatbot/logs`, `GET /api/chatbot/stats` (middleware `auth:sanctum` & role `admin,kepala`)

### Task 4: Feature Automated Testing (`ChatbotWebhookTest.php`)
- [ ] Buat file test `tests/Feature/ChatbotWebhookTest.php`.
- [ ] Buat skenario pengujian komprehensif:
  - Uji respon cek SPP saat ada tunggakan.
  - Uji respon cek SPP saat lunas.
  - Uji respon cek presensi hari ini & rekap bulanan.
  - Uji penanganan nomor belum terdaftar.
  - Uji penanganan multi-santri bersaudara (pemilihan anak 1 & 2).
  - Uji pencatatan log pada tabel `chatbot_logs`.
  - Uji endpoint simulator admin.
- [ ] Jalankan test hingga 100% lulus.

### Task 5: Antarmuka Web Admin & Simulator Chatbot (`ChatbotView.vue`)
- [ ] Buat komponen `resources/js/views/chatbot/ChatbotView.vue`:
  - **Tab 1: Log Percakapan Realtime**: KPI Cards statistik + Tabel interaktif riwayat chat + Filter intent/tanggal + Search bar.
  - **Tab 2: Interactive Chatbot Simulator**: Layar simulasi chat interaktif (bisa ganti nomor sampel santri & ketik pertanyaan langsung untuk demo sidang).
  - **Tab 3: Konfigurasi Webhook**: URL Webhook Fonnte dengan tombol salin cepat + panduan integrasi Fonnte.
- [ ] Daftarkan rute `/chatbot` di `resources/js/router/index.js` dengan meta `roles: ['admin', 'kepala']`.
- [ ] Tambahkan menu `🤖 Chatbot WhatsApp` pada navigasi sidebar di `resources/js/layouts/AppLayout.vue`.

### Task 6: Formatting, Frontend Build & Final Verification
- [ ] Format kode PHP menggunakan `vendor/bin/pint --format agent`.
- [ ] Jalankan seluruh rangkaian test backend `php artisan test --compact`.
- [ ] Jalankan kompilasi frontend bundle `npm run build`.
- [ ] Verifikasi seluruh alur dari simulator hingga pencatatan log.
