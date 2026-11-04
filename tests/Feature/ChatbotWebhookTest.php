<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\Agenda;
use App\Models\HariLibur;
use App\Models\Kelas;
use App\Models\Pengaturan;
use App\Models\Siswa;
use App\Models\TagihanSpp;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ChatbotWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected Kelas $kelas;

    protected Siswa $siswa1;

    protected Siswa $siswa2;

    protected User $adminUser;

    protected User $kepalaUser;

    protected User $guruUser;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->kelas = Kelas::create([
            'nama_kelas' => 'JILID 1',
            'warna_label' => '#f44336',
        ]);

        $this->siswa1 = Siswa::create([
            'nisn' => '1001',
            'nama_lengkap' => 'Ahmad Fauzi',
            'jenis_kelamin' => 'L',
            'id_kelas' => $this->kelas->id,
            'no_hp_ortu' => '081234567890',
            'nama_ortu' => 'Bapak Fauzi',
            'status' => 'aktif',
        ]);

        $this->siswa2 = Siswa::create([
            'nisn' => '1002',
            'nama_lengkap' => 'Fatimah Az-Zahra',
            'jenis_kelamin' => 'P',
            'id_kelas' => $this->kelas->id,
            'no_hp_ortu' => '081234567890', // Nomor sama (Kakak-Adik)
            'nama_ortu' => 'Bapak Fauzi',
            'status' => 'aktif',
        ]);

        $this->adminUser = User::factory()->create([
            'username' => 'admin_test',
            'role' => 'admin',
        ]);

        $this->kepalaUser = User::factory()->create([
            'username' => 'kepala_test',
            'role' => 'kepala',
        ]);

        $this->guruUser = User::factory()->create([
            'username' => 'guru_test',
            'role' => 'guru',
        ]);
    }

    public function test_webhook_requires_sender(): void
    {
        $response = $this->postJson('/api/webhook/whatsapp', [
            'message' => 'Halo',
        ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    public function test_webhook_empty_ping_returns_200(): void
    {
        $response = $this->postJson('/api/webhook/whatsapp', []);

        $response->assertStatus(200)
            ->assertJson(['status' => true]);
    }

    public function test_webhook_responds_spp_notice_for_registered_student(): void
    {
        // Berikan nomor unik khusus siswa1 untuk tes single student
        $this->siswa2->update(['no_hp_ortu' => '089999999999']);

        $now = Carbon::now('Asia/Jakarta');
        TagihanSpp::create([
            'id_siswa' => $this->siswa1->id,
            'bulan' => (int) $now->month,
            'tahun' => (int) $now->year,
            'tahun_ajaran' => '2026/2027',
            'nominal_tagihan' => 25000,
            'status' => 'belum_lunas',
        ]);

        $response = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => 'anak saya nunggak berapa bulan',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'spp_notice',
            ]);

        $reply = $response->json('reply');
        $this->assertStringContainsString('Ahmad Fauzi', $reply);
        $this->assertStringContainsString('Menunggak 1 bulan', $reply);
        $this->assertStringContainsString('Rp 25.000', $reply);
        $this->assertStringNotContainsString("Assalamu'alaikum", $reply);
    }

    public function test_webhook_responds_spp_lunas_when_no_arrears(): void
    {
        $this->siswa2->update(['no_hp_ortu' => '089999999999']);

        $now = Carbon::now('Asia/Jakarta');
        TagihanSpp::create([
            'id_siswa' => $this->siswa1->id,
            'bulan' => (int) $now->month,
            'tahun' => (int) $now->year,
            'tahun_ajaran' => '2026/2027',
            'nominal_tagihan' => 25000,
            'status' => 'lunas',
        ]);

        $response = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => 'Cek tagihan spp anak saya',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'spp_notice',
            ]);

        $reply = $response->json('reply');
        $this->assertStringContainsString('Ahmad Fauzi', $reply);
        $this->assertStringContainsString('LUNAS', $reply);
        $this->assertStringNotContainsString("Assalamu'alaikum", $reply);
    }

    public function test_webhook_responds_attendance_today_and_monthly_recap(): void
    {
        $this->siswa2->update(['no_hp_ortu' => '089999999999']);

        Absensi::create([
            'id_siswa' => $this->siswa1->id,
            'tanggal' => Carbon::today()->toDateString(),
            'waktu_masuk' => '15:30:00',
            'status' => 'Hadir',
            'metode_scan' => 'qr_kamera',
        ]);

        $response = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => 'Gimana presensi dan kehadiran Ahmad hari ini?',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'absensi',
            ]);

        $reply = $response->json('reply');
        $this->assertStringContainsString('Ahmad Fauzi', $reply);
        $this->assertStringContainsString('HADIR TEPAT WAKTU', $reply);
        $this->assertStringContainsString('15:30:00', $reply);
        $this->assertStringNotContainsString("Assalamu'alaikum", $reply);
    }

    public function test_webhook_responds_holiday_info_without_internal_agenda(): void
    {
        HariLibur::create([
            'tanggal_mulai' => Carbon::tomorrow()->toDateString(),
            'tanggal_selesai' => Carbon::tomorrow()->toDateString(),
            'keterangan' => 'Libur Isra Miraj',
        ]);

        Agenda::create([
            'tanggal' => Carbon::today()->addDays(3)->toDateString(),
            'judul' => 'Rapat Bulanan Internal',
            'keterangan' => 'Khusus dewan guru',
        ]);

        $response = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => 'Bulan ini apakah ada hari libur selain hari libur rutinan?',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'agenda',
            ]);

        $reply = $response->json('reply');
        $this->assertStringContainsString('Libur Isra Miraj', $reply);
        $this->assertStringNotContainsString('Rapat Bulanan Internal', $reply);
        $this->assertStringNotContainsString("Assalamu'alaikum", $reply);
    }

    public function test_webhook_handles_unregistered_phone(): void
    {
        $response = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '628999111222',
            'message' => 'Halo assalamu\'alaikum ustadz',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'unregistered',
            ]);

        $reply = $response->json('reply');
        $this->assertStringContainsString('belum terdaftar', $reply);
        $this->assertStringContainsString('NISN', $reply);
    }

    public function test_webhook_handles_direct_nisn_in_message(): void
    {
        $response = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '628999111222',
            'message' => 'NISN 1001 cek kehadiran',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'absensi',
            ]);

        $reply = $response->json('reply');
        $this->assertStringContainsString('Ahmad Fauzi', $reply);
    }

    public function test_webhook_handles_multi_student_selection(): void
    {
        // Nomor 081234567890 memiliki 2 anak: Ahmad Fauzi & Fatimah Az-Zahra
        $response = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => 'Halo ustadz',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'multi_santri',
            ]);

        $reply = $response->json('reply');
        $this->assertStringContainsString('Ahmad Fauzi', $reply);
        $this->assertStringContainsString('Fatimah Az-Zahra', $reply);
        $this->assertStringContainsString('*1*', $reply);

        // Pilih anak ke-2 (Fatimah)
        $responseSelection = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => '2',
        ]);

        $responseSelection->assertStatus(200);
        $this->assertStringContainsString('Fatimah Az-Zahra', $responseSelection->json('reply'));

        // Pertanyaan berikutnya otomatis untuk Fatimah
        $responseNext = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => 'Cek absensi anak saya',
        ]);

        $responseNext->assertStatus(200)
            ->assertJson(['intent' => 'absensi']);
        $this->assertStringContainsString('Fatimah Az-Zahra', $responseNext->json('reply'));
    }

    public function test_chatbot_logs_recorded_in_database(): void
    {
        $this->siswa2->update(['no_hp_ortu' => '089999999999']);

        $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => 'Cek kehadiran anak saya',
        ]);

        $this->assertDatabaseHas('chatbot_logs', [
            'no_pengirim' => '6281234567890',
            'intent_terdeteksi' => 'absensi',
            'id_siswa' => $this->siswa1->id,
            'status' => 'sukses',
        ]);
    }

    public function test_admin_can_access_simulator_and_logs(): void
    {
        // Admin test
        $responseAdmin = $this->actingAs($this->adminUser)
            ->postJson('/api/chatbot/simulate', [
                'phone' => '081234567890',
                'message' => 'Cek kehadiran',
            ]);

        $responseAdmin->assertStatus(200)
            ->assertJson(['success' => true]);

        $responseLogs = $this->actingAs($this->adminUser)
            ->getJson('/api/chatbot/logs');

        $responseLogs->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_other_roles_cannot_access_chatbot_admin_routes(): void
    {
        // Kepala TPQ should not access chatbot
        $responseKepala = $this->actingAs($this->kepalaUser)
            ->getJson('/api/chatbot/logs');
        $responseKepala->assertStatus(403);

        // Guru should not access chatbot
        $responseGuru = $this->actingAs($this->guruUser)
            ->getJson('/api/chatbot/logs');
        $responseGuru->assertStatus(403);
    }

    public function test_webhook_handles_thank_you_appreciation_naturally_even_with_multi_students(): void
    {
        $response = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => 'baik terimakasih',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'apresiasi',
            ]);

        $reply = $response->json('reply');
        $this->assertStringContainsString('Sama-sama Bapak/Ibu', $reply);
        $this->assertStringContainsString('Ahmad Fauzi', $reply);
        $this->assertStringContainsString('Fatimah Az-Zahra', $reply);
    }

    public function test_webhook_returns_both_children_attendance_when_multi_student_parent_asks_directly(): void
    {
        $response = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => 'gimana absensi anak saya',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'absensi',
            ]);

        $reply = $response->json('reply');
        $this->assertStringContainsString('Ahmad Fauzi', $reply);
        $this->assertStringContainsString('Fatimah Az-Zahra', $reply);
    }

    public function test_webhook_matches_nickname_and_inline_number_and_izin_sakit(): void
    {
        // 1. Inline number: "berapa tunggakan SPP anak saya 1"
        $resSpp = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => 'berapa tunggakan SPP anak saya 1',
        ]);

        $resSpp->assertStatus(200)
            ->assertJson(['intent' => 'spp_notice']);
        $this->assertStringContainsString('Ahmad Fauzi', $resSpp->json('reply'));

        // 2. Nickname match: "fatimah"
        $resNick = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => 'gimana absensi fatimah hari ini',
        ]);

        $resNick->assertStatus(200)
            ->assertJson(['intent' => 'absensi']);
        $this->assertStringContainsString('Fatimah Az-Zahra', $resNick->json('reply'));

        // 3. Izin sakit notification
        $resIzin = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => 'ustadz mohon izin hari ini ahmad tidak bisa hadir karena sedang sakit demam',
        ]);

        $resIzin->assertStatus(200)
            ->assertJson(['intent' => 'izin_sakit']);
        $this->assertStringContainsString('Syafahullah', $resIzin->json('reply'));
    }

    public function test_webhook_multi_student_consecutive_questions_and_follow_up_other_child(): void
    {
        // Setup tagihan untuk kedua anak
        $now = Carbon::now('Asia/Jakarta');
        TagihanSpp::create([
            'id_siswa' => $this->siswa1->id,
            'bulan' => (int) $now->month,
            'tahun' => (int) $now->year,
            'tahun_ajaran' => '2026/2027',
            'nominal_tagihan' => 15000,
            'status' => 'belum_lunas',
        ]);
        TagihanSpp::create([
            'id_siswa' => $this->siswa2->id,
            'bulan' => (int) $now->month,
            'tahun' => (int) $now->year,
            'tahun_ajaran' => '2026/2027',
            'nominal_tagihan' => 15000,
            'status' => 'belum_lunas',
        ]);

        // 1. Wali bertanya SPP: "anak saya nunggak berapa bulan?"
        $resSpp = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => 'anak saya nunggak berapa bulan?',
        ]);

        $resSpp->assertStatus(200);
        $replySpp = $resSpp->json('reply');
        $this->assertStringContainsString('Ahmad Fauzi', $replySpp);
        $this->assertStringContainsString('Fatimah Az-Zahra', $replySpp);
        // Pastikan tidak ada "SANTRI 1:" atau "SANTRI 2:"
        $this->assertStringNotContainsString('SANTRI 1:', $replySpp);
        $this->assertStringNotContainsString('SANTRI 2:', $replySpp);

        // 2. Wali bertanya berturut-turut: "kalau absensi anak saya"
        $resAbsensi = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => 'kalau absensi anak saya',
        ]);

        $resAbsensi->assertStatus(200);
        $replyAbsensi = $resAbsensi->json('reply');
        // KEDUA anak harus terjawab, bukan cuma satu anak!
        $this->assertStringContainsString('Ahmad Fauzi', $replyAbsensi);
        $this->assertStringContainsString('Fatimah Az-Zahra', $replyAbsensi);
        // Pastikan tidak ada "SANTRI 1:" atau "SANTRI 2:"
        $this->assertStringNotContainsString('SANTRI 1:', $replyAbsensi);
        $this->assertStringNotContainsString('SANTRI 2:', $replyAbsensi);

        // 3. Wali bertanya secara spesifik ke anak 2 lalu tanya "anak sya yang satunya lagi"
        $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => 'gimana absensi fatimah',
        ]);

        $resOther = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => 'anak sya yang satunya lagi',
        ]);

        $resOther->assertStatus(200);
        $replyOther = $resOther->json('reply');
        // Harus beralih ke Ahmad Fauzi dan menjawab absensi Ahmad Fauzi
        $this->assertStringContainsString('Ahmad Fauzi', $replyOther);
        $this->assertStringContainsString('Absensi ananda Ahmad Fauzi', $replyOther);
    }

    public function test_webhook_handles_previous_month_and_colloquial_frequency_questions(): void
    {
        // Catat absensi Ahmad Fauzi di bulan Oktober 2026 (bulan lalu)
        Absensi::create([
            'id_siswa' => $this->siswa1->id,
            'tanggal' => '2026-10-15',
            'waktu_masuk' => '15:30:00',
            'status' => 'Hadir',
            'metode_scan' => 'qr_kamera',
        ]);
        Absensi::create([
            'id_siswa' => $this->siswa1->id,
            'tanggal' => '2026-10-16',
            'waktu_masuk' => '15:45:00',
            'status' => 'Terlambat',
            'metode_scan' => 'qr_kamera',
        ]);

        // Catat absensi Fatimah di bulan Oktober 2026 (bulan lalu)
        Absensi::create([
            'id_siswa' => $this->siswa2->id,
            'tanggal' => '2026-10-15',
            'waktu_masuk' => '15:30:00',
            'status' => 'Hadir',
            'metode_scan' => 'qr_kamera',
        ]);

        // 1. Wali bertanya pertanyaan lanjutan tidak spesifik: "kalau bulan sebelumnya berpa kali?"
        $response = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => 'kalau bulan sebelumnya berpa kali?',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'absensi',
            ]);

        $reply = $response->json('reply');
        // Harus mendeteksi bulan Oktober 2026 dan memuat data kedua anak
        $this->assertStringContainsString('Oktober 2026', $reply);
        $this->assertStringContainsString('Ahmad Fauzi', $reply);
        $this->assertStringContainsString('Fatimah Az-Zahra', $reply);
        $this->assertStringContainsString('1 kali', $reply);
        $this->assertStringNotContainsString('SANTRI 1:', $reply);
        $this->assertStringNotContainsString("Assalamu'alaikum", $reply);

        // 2. Wali bertanya spesifik nama bulan: "rekap bulan oktober"
        $resOkt = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => 'rekap bulan oktober',
        ]);

        $resOkt->assertStatus(200);
        $this->assertStringContainsString('Oktober', $resOkt->json('reply'));

        // 3. Wali bertanya kemarin: "kemarin masuk gak?"
        $resKemarin = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => 'kemarin masuk gak?',
        ]);

        $resKemarin->assertStatus(200)
            ->assertJson(['intent' => 'absensi']);
    }

    public function test_webhook_responds_to_payment_target_inquiry_and_tunggakan_bendahara_appeal(): void
    {
        // Setup tagihan SPP menunggak untuk kedua anak
        $now = Carbon::now('Asia/Jakarta');
        TagihanSpp::create([
            'id_siswa' => $this->siswa1->id,
            'bulan' => (int) $now->month,
            'tahun' => (int) $now->year,
            'tahun_ajaran' => '2026/2027',
            'nominal_tagihan' => 15000,
            'status' => 'belum_lunas',
        ]);
        TagihanSpp::create([
            'id_siswa' => $this->siswa2->id,
            'bulan' => (int) $now->month,
            'tahun' => (int) $now->year,
            'tahun_ajaran' => '2026/2027',
            'nominal_tagihan' => 15000,
            'status' => 'belum_lunas',
        ]);

        // Simpan no hp bendahara di pengaturan
        Pengaturan::setValue('no_hp_bendahara', '0858-9914-5514');

        // 1. Wali bertanya spesifik kemana/kepada siapa membayar SPP (sesuai screenshot user)
        $resTarget = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => 'kepada siapa kalau mau bayar spp',
        ]);

        $resTarget->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'spp_notice',
            ]);

        $replyTarget = $resTarget->json('reply');
        // Tidak membombardir status SPP anak jika hanya bertanya kepada siapa membayar
        $this->assertStringNotContainsString('Ahmad Fauzi', $replyTarget);
        $this->assertStringNotContainsString('Fatimah Az-Zahra', $replyTarget);
        $this->assertStringNotContainsString('Menunggak', $replyTarget);
        // Harus langsung to-the-point memuat info bendahara dan nomor kontak
        $this->assertStringContainsString('Pembayaran SPP dapat diselesaikan melalui Bendahara', $replyTarget);
        $this->assertStringContainsString('0858-9914-5514', $replyTarget);
        $this->assertStringContainsString('Kantor / Sekretariat TPQ', $replyTarget);
        // Harus memuat kalimat himbauan bayar ke Bendahara
        $this->assertStringContainsString('Besar harapan kami agar Bapak/Ibu dapat segera menyelesaikan kewajiban administrasi ini melalui Bendahara', $replyTarget);
        $this->assertStringContainsString('Cek SPP', $replyTarget);

        // 2. Wali bertanya tanpa kata SPP: "kepada siapa kalau mau bayar" (sesuai pesan persis user)
        $resTarget2 = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => 'kepada siapa kalau mau bayar',
        ]);
        $resTarget2->assertStatus(200);
        $replyTarget2 = $resTarget2->json('reply');
        $this->assertStringNotContainsString('Ahmad Fauzi', $replyTarget2);
        $this->assertStringContainsString('Pembayaran SPP dapat diselesaikan melalui Bendahara', $replyTarget2);

        // 3. Wali bertanya tunggakan biasa, bot menampilkan rincian anak + menyertakan himbauan Bendahara karena ada tunggakan
        $resTunggakan = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => 'anak saya nunggak berapa bulan?',
        ]);

        $resTunggakan->assertStatus(200);
        $replyTunggakan = $resTunggakan->json('reply');
        $this->assertStringContainsString('Ahmad Fauzi', $replyTunggakan);
        $this->assertStringContainsString('Fatimah Az-Zahra', $replyTunggakan);
        $this->assertStringContainsString('Menunggak', $replyTunggakan);
        $this->assertStringContainsString('Besar harapan kami agar Bapak/Ibu dapat segera menyelesaikan kewajiban administrasi ini melalui Bendahara', $replyTunggakan);
        $this->assertStringContainsString('0858-9914-5514', $replyTunggakan);

        // 4. Wali bertanya gabungan: status tunggakan DAN kemana bayar
        $resCombined = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => 'anak saya nunggak berapa bulan dan bayar ke siapa?',
        ]);
        $resCombined->assertStatus(200);
        $replyCombined = $resCombined->json('reply');
        // Keduanya harus muncul: status tagihan anak dan info Bendahara
        $this->assertStringContainsString('Ahmad Fauzi', $replyCombined);
        $this->assertStringContainsString('Fatimah Az-Zahra', $replyCombined);
        $this->assertStringContainsString('Pembayaran SPP dapat diselesaikan melalui Bendahara', $replyCombined);
    }

    public function test_spp_response_includes_default_bendahara_number_and_numeric_menu_navigation(): void
    {
        $this->siswa2->update(['no_hp_ortu' => '089999999999']);
        Pengaturan::where('key', 'no_hp_bendahara')->delete();

        // 1. Cek SPP menyertakan nomor resmi bendahara 085235567818
        $resSpp = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => 'cek spp',
        ]);
        $resSpp->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'spp_notice',
            ]);
        $this->assertStringContainsString('085235567818', $resSpp->json('reply'));

        // 2. Tanya ke siapa bayar menyertakan nomor bendahara 085235567818
        $resTarget = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => 'bayar ke siapa?',
        ]);
        $resTarget->assertStatus(200);
        $this->assertStringContainsString('085235567818', $resTarget->json('reply'));

        // 3. Ketik angka 1 memilih topik Absensi
        $res1 = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => '1',
        ]);
        $res1->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'absensi',
            ]);

        // 4. Ketik angka 2 memilih topik SPP
        $res2 = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => '2',
        ]);
        $res2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'spp_notice',
            ]);
        $this->assertStringContainsString('085235567818', $res2->json('reply'));

        // 5. Ketik angka 3 memilih topik Hari Libur
        $res3 = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => '3',
        ]);
        $res3->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'agenda',
            ]);
    }
}
