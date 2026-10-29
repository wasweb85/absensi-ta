# WhatsApp Delivery Log & Status Tracking Specification

- **Tanggal Pembuatan**: 2026-10-26
- **Status**: Disetujui (Approved)
- **Modul**: Integrasi WhatsApp & Gateway Fonnte
- **Target Role Pengguna**: Admin & Bendahara

---

## 1. Latar Belakang & Tujuan

Aplikasi saat ini memiliki modul pengiriman pesan WhatsApp (pengingat SPP japri, broadcast jilid, rekap wali kelas, dan balasan chatbot) melalui gateway Fonnte. Namun, admin dan bendahara belum memiliki sarana terpusat untuk memantau status siklus hidup pesan keluar, seperti:
- Apakah pesan sudah dikirim ke gateway?
- Apakah pesan sudah tersampaikan ke HP penerima (centang 2 abu-abu)?
- Apakah pesan sudah dibuka dan dibaca (centang 2 biru)?
- Apakah pesan gagal terkirim (nomor salah, kuota habis, perangkat terputus)?

Tujuan spesifikasi ini adalah membangun **WhatsApp Delivery Log** yang sentral untuk seluruh pesan keluar sistem, menyediakan kartu statistik agregat, tabel pemantauan realtime via webhook Fonnte, tombol kirim ulang (resend) per pesan, serta tombol kirim ulang massal (*bulk resend*) untuk pesan yang gagal.

---

## 2. Arsitektur & Skema Database

### 2.1 Tabel `whatsapp_logs`

Tabel baru `whatsapp_logs` mencatat setiap pesan keluar dari sistem beserta siklus hidup statusnya.

| Kolom | Tipe Data | Keterangan |
| :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED` (PK, Auto Increment) | ID unik log pengiriman. |
| `fonnte_id` | `VARCHAR(100)` NULL, INDEX | ID unik pesan yang dikembalikan oleh API Fonnte untuk matching status webhook. |
| `nomor_tujuan` | `VARCHAR(25)`, INDEX | Nomor WhatsApp tujuan (format standar `628...`). |
| `nama_penerima` | `VARCHAR(100)` NULL | Nama santri, orang tua, atau wali kelas penerima. |
| `pesan` | `TEXT` | Isi pesan teks yang dikirimkan. |
| `tipe_pesan` | `VARCHAR(50)`, INDEX | Kategori pesan: `spp_japri`, `spp_broadcast`, `chatbot_reply`, `absensi`, `manual`. |
| `status` | `ENUM('pending', 'sent', 'delivered', 'read', 'failed')` | Status pengiriman pesan saat ini (default: `pending`). |
| `keterangan_error` | `TEXT` NULL | Penjelasan kesalahan jika gateway gagal atau menolak pengiriman. |
| `retry_count` | `INT UNSIGNED` DEFAULT 0 | Jumlah pengulangan pengiriman (resend). |
| `last_retried_at` | `TIMESTAMP` NULL | Waktu terakhir kali tombol kirim ulang ditekan. |
| `sent_at` | `TIMESTAMP` NULL | Waktu pesan berhasil terkirim ke server WhatsApp (centang 1). |
| `delivered_at` | `TIMESTAMP` NULL | Waktu pesan tersampaikan di perangkat penerima (centang 2 abu-abu). |
| `read_at` | `TIMESTAMP` NULL | Waktu pesan dibaca oleh penerima (centang 2 biru). |
| `created_at` | `TIMESTAMP` | Waktu log dibuat. |
| `updated_at` | `TIMESTAMP` | Waktu perubahan terakhir data log. |

### 2.2 Model Eloquent `App\Models\WhatsAppLog`

Model mencakup:
- **Mass Assignment Fillable**: `['fonnte_id', 'nomor_tujuan', 'nama_penerima', 'pesan', 'tipe_pesan', 'status', 'keterangan_error', 'retry_count', 'last_retried_at', 'sent_at', 'delivered_at', 'read_at']`
- **Casts**: `sent_at` (datetime), `delivered_at` (datetime), `read_at` (datetime), `last_retried_at` (datetime), `retry_count` (integer).
- **Query Scopes**:
  - `scopeFailed($query)`: memfilter status `failed` atau `pending`.
  - `scopeDelivered($query)`: memfilter status `delivered`.
  - `scopeRead($query)`: memfilter status `read`.
  - `scopeSearch($query, $keyword)`: pencarian pada `nomor_tujuan`, `nama_penerima`, dan `pesan`.
  - `scopeFilterStatus($query, $status)`: filter berdasarkan status spesifik.
  - `scopeFilterTipe($query, $tipe)`: filter kategori pesan.
- **Helper Methods**:
  - `markAsSent(string $fonnteId): void`
  - `markAsDelivered(): void`
  - `markAsRead(): void`
  - `markAsFailed(string $error): void`
  - `recordRetry(): void`

---

## 3. Integrasi Backend & Alur Data

### 3.1 Perekaman Terpusat di `WhatsAppService`

Metode `sendViaFonnte` disesuaikan untuk menerima parameter metadata opsional:
```php
public function sendViaFonnte(
    string $phone, 
    string $message, 
    ?string $customToken = null, 
    array $metadata = []
): array
```
1. Jika `$metadata['log_id']` belum ada:
   - Dibuat entri baru `WhatsAppLog` dengan status `pending`, `nomor_tujuan`, `nama_penerima`, `pesan`, dan `tipe_pesan`.
2. Jika `$metadata['log_id']` ada (kasus kirim ulang):
   - Ambil data log yang ada dan panggil `$log->recordRetry()`.
3. Setelah HTTP request ke endpoint `https://api.fonnte.com/send`:
   - Jika HTTP berhasil dan `status == true`:
     - Ambil ID pesan dari Fonnte (misal: `$json['id'][0]` atau `$json['id']`).
     - Panggil `$log->markAsSent($fonnteId)`.
   - Jika HTTP gagal atau status `false`:
     - Panggil `$log->markAsFailed($reason)`.
4. Mengembalikan respons array standar yang menyertakan `'log_id' => $log->id`.

### 3.2 Antrean Background `SendWhatsAppMessageJob`

Job antrean diperbarui agar menerima parameter `$logId = null`:
```php
public function __construct(
    public string $phone,
    public string $message,
    public ?string $customToken = null,
    public ?int $logId = null,
    public array $metadata = []
)
```
Saat job dijalankan di background, job meneruskan `$logId` ke `WhatsAppService` sehingga tidak terjadi duplikasi baris log pada saat percobaan ulang otomatis.

### 3.3 Penanganan Webhook Fonnte (Dual Payload Detection)

Endpoint `/api/webhook/whatsapp` di `ChatbotController` (atau controller webhook terpisah) menangani 2 macam event dari Fonnte:
1. **Event Pesan Masuk**:
   - Terdeteksi saat request memiliki field `sender` / `from` dan `message` / `body`.
   - Diteruskan ke `ChatbotService::handleIncomingMessage()`.
2. **Event Pembaruan Status Pesan (Message Status Update)**:
   - Terdeteksi saat request memiliki field `id` dan `state` (misal: `state == 'sent'`, `state == 'delivered'`, `state == 'read'`, dsb.).
   - Sistem mencari log via `WhatsAppLog::where('fonnte_id', $request->input('id'))->first()`.
   - Logika pembaruan:
     - `state == 'delivered'` $\rightarrow$ `$log->markAsDelivered()`
     - `state == 'read'` $\rightarrow$ `$log->markAsRead()`
     - `state == 'sent'` $\rightarrow$ `$log->markAsSent($request->input('id'))`
     - `in_array($state, ['failed', 'invalid', 'expired'])` $\rightarrow$ `$log->markAsFailed("Fonnte: {$state}")`
   - Segera mengembalikan respons JSON `['status' => true, 'message' => 'Status log diperbarui']` dengan kode status 200 OK.

---

## 4. Spesifikasi REST API

Seluruh endpoint di bawah ini diproteksi oleh middleware autentikasi `auth:sanctum` dan otorisasi role `admin` & `bendahara`.

### 4.1 `GET /api/wa/logs`
- **Query Params**:
  - `page` (int, default: 1)
  - `per_page` (int, default: 15)
  - `status` (string, `all|sent|delivered|read|failed|pending`)
  - `search` (string, pencarian nama, nomor HP, atau isi pesan)
  - `tipe_pesan` (string, misal `spp_japri`, `spp_broadcast`)
  - `start_date` & `end_date` (date Y-m-d)
- **Response Format**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "fonnte_id": "msg_982341",
      "nomor_tujuan": "628123456789",
      "nama_penerima": "Ahmad Fauzi (Wali)",
      "pesan": "Assalamu'alaikum... SPP Bulan Oktober belum lunas...",
      "tipe_pesan": "spp_japri",
      "status": "read",
      "keterangan_error": null,
      "retry_count": 0,
      "sent_at": "2026-10-26 08:00:10",
      "delivered_at": "2026-10-26 08:00:15",
      "read_at": "2026-10-26 08:05:22",
      "created_at": "2026-10-26 08:00:00"
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 65
  },
  "summary": {
    "total": 65,
    "sent": 12,
    "delivered": 28,
    "read": 20,
    "failed": 5
  }
}
```

### 4.2 `POST /api/wa/logs/{id}/retry`
- Mengirim ulang satu pesan spesifik yang gagal atau tertunda.
- **Response**: Mengembalikan status sukses dan data log yang telah diperbarui.

### 4.3 `POST /api/wa/logs/retry-failed`
- Mengambil seluruh log dengan status `failed` atau `pending`, lalu memasukkannya ke antrean `SendWhatsAppMessageJob`.
- **Response**: `{"success": true, "retried_count": 5, "message": "5 pesan berhasil dimasukkan ke antrean pengiriman ulang."}`

### 4.4 `DELETE /api/wa/logs/{id}`
- Menghapus 1 record log dari database.

---

## 5. Antarmuka Pengguna (UI/UX) Frontend

### 5.1 Penempatan & Rute Navigasi
- **Rute Frontend**: `/wa/delivery-log`
- **Komponen**: `resources/js/views/wa/DeliveryLogView.vue`
- **Sidebar**: Ditambahkan menu "Log Pengiriman WA" pada `AppLayout.vue` di kelompok Komunikasi / WhatsApp.

### 5.2 Komponen Visual Dashboard
1. **Header & Aksi Global**:
   - Judul: "Monitoring & Delivery Log WhatsApp"
   - Deskripsi: "Pantau status pengiriman centang pesan, laporan keterbacaan, dan kelola pengiriman ulang pesan gagal."
   - Tombol **Refresh Data** dan tombol **Kirim Ulang Semua Gagal** (dengan badge counter pesan gagal).
2. **5 Kartu Counter Interaktif (Quick Filters)**:
   - Kartu 1: **Total Pesan** (Netral)
   - Kartu 2: **Terkirim** (Centang 1 Abu-Abu)
   - Kartu 3: **Tersampaikan** (Centang 2 Abu-Abu)
   - Kartu 4: **Sudah Dibaca** (Centang 2 Biru)
   - Kartu 5: **Gagal / Pending** (Merah / Warning)
   - *Interaktivitas*: Mengklik salah satu kartu otomatis mengubah filter tabel di bawahnya.
3. **Pencarian & Filter Bar**:
   - Input search instan (debounce 300ms).
   - Filter dropdown tipe pesan.
   - Filter tanggal.
4. **Tabel Data Responsif**:
   - Indikator centang visual WhatsApp yang autentik:
     - Centang 1 (Terkirim ke gateway)
     - Centang 2 Abu (Tersampaikan ke device penerima)
     - Centang 2 Biru (Telah dibaca penerima)
     - Tanda silang merah / warning (Gagal)
   - Tombol "Lihat Pesan" yang membuka modal preview chat bubble WhatsApp.
   - Tombol "Kirim Ulang" aktif pada baris yang gagal atau pending.
   - Tombol "Detail Riwayat" yang menampilkan modal informasi timeline (jam kirim, jam delivered, jam dibaca).

---

## 6. Rencana Pengujian (Testing Strategy)

1. **Unit & Feature Test Backend (PHPUnit)**:
   - `WhatsAppLogTest`: Memastikan pembuatan log saat pengiriman pesan via `WhatsAppService`.
   - `WhatsAppWebhookStatusTest`: Simulasi webhook callback dari Fonnte (`delivered`, `read`, `failed`) dan verifikasi perubahan status di database.
   - `WhatsAppDeliveryLogApiTest`:
     - `GET /api/wa/logs` mengembalikan struktur JSON terpaginasi beserta agregat summary count yang akurat.
     - `POST /api/wa/logs/{id}/retry` berhasil menambah `retry_count` dan memicu pengiriman ulang.
     - `POST /api/wa/logs/retry-failed` memasukkan seluruh pesan berstatus failed ke antrean job.
2. **Frontend Test**:
   - Pengujian integrasi antarmuka untuk filter status, pagination, modal preview pesan, dan trigger tombol kirim ulang.

---

## 7. Penanganan Kasus Khusus (Edge Cases)

- **Pengujian di Lingkungan Lokal (Laragon)**: Karena Fonnte tidak bisa mengirim webhook ke `http://localhost` tanpa tunnel, disediakan tombol uji coba simulator status di view dan endpoint `/api/webhook/whatsapp` dapat di-mock atau dihubungkan via tunnel (ngrok/Cloudflare).
- **Nomor Tidak Valid**: Fonnte mengembalikan `status: false` dengan pesan "invalid number". Sistem langsung menandai log sebagai `failed` dengan keterangan error yang jelas agar admin tidak terus mengulang nomor yang salah.
- **Pesan Duplikat saat Retry**: Tombol kirim ulang mengunci tombol (loading state) dan memperbarui record lama dengan mencatat `retry_count` daripada menyalin record baru.
