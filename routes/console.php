<?php

use App\Models\Absensi;
use App\Models\HariLibur;
use App\Models\Pengaturan;
use App\Models\Siswa;
use App\Services\GoogleSheetSyncService;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('absensi:close-daily', function () {
    $now = Carbon::now('Asia/Jakarta');
    $today = $now->format('Y-m-d');

    // Cek apakah hari ini hari libur
    $isHoliday = HariLibur::where('tanggal_mulai', '<=', $today)
        ->where('tanggal_selesai', '>=', $today)
        ->exists();

    if ($isHoliday) {
        $this->info("Hari ini libur ({$today}). Penutupan absensi dilewati.");

        return;
    }

    // Cek hari aktif
    $dayNamesIndo = [
        0 => 'minggu', 1 => 'senin', 2 => 'selasa', 3 => 'rabu',
        4 => 'kamis', 5 => 'jumat', 6 => 'sabtu',
    ];
    $hariAktifStr = Pengaturan::getValue('hari_aktif', 'Senin,Selasa,Rabu,Kamis,Jumat,Sabtu');
    $activeDays = array_map('trim', explode(',', strtolower($hariAktifStr)));
    $todayName = $dayNamesIndo[$now->dayOfWeek] ?? '';

    if (! in_array($todayName, $activeDays, true)) {
        $this->info("Hari ini ({$todayName}) bukan hari aktif kegiatan. Penutupan absensi dilewati.");

        return;
    }

    // Cari santri aktif yang belum absen hari ini
    $santriBelumAbsen = Siswa::where('status', 'aktif')
        ->whereDoesntHave('absensi', function ($q) use ($today) {
            $q->where('tanggal', $today);
        })
        ->get();

    $count = 0;
    foreach ($santriBelumAbsen as $siswa) {
        Absensi::create([
            'id_siswa' => $siswa->id,
            'tanggal' => $today,
            'status' => 'Alpa',
            'metode_scan' => 'manual',
            'keterangan' => 'Otomatis: Tidak hadir hingga jam pulang',
        ]);
        $count++;
    }

    $this->info("Penutupan absensi harian selesai. {$count} santri ditandai Alpa.");
})->purpose('Menutup absensi harian dan menandai santri yang tidak hadir sebagai Alpa');

Artisan::command('santri:sync-sheets {url}', function (string $url, GoogleSheetSyncService $service) {
    $this->info('Menghubungi Google Spreadsheet...');
    try {
        $rows = $service->fetchAndParse($url);
        $result = $service->syncData($rows, true);
        $this->info('Sinkronisasi santri berhasil!');
        $this->table(
            ['Total Baris', 'Santri Baru Dibuat', 'Santri Diperbarui', 'Gagal / Dilewati'],
            [[$result['total_rows'], $result['created'], $result['updated'], $result['failed']]]
        );
        if ($result['failed'] > 0) {
            $this->warn('Daftar baris yang dilewati:');
            foreach ($result['errors'] as $err) {
                $this->line("- Baris #{$err['row']} ({$err['nama']} / {$err['nisn']}): {$err['reason']}");
            }
        }
    } catch (Throwable $e) {
        $this->error('Gagal sinkronisasi: '.$e->getMessage());
    }
})->purpose('Sinkronisasi data santri secara otomatis langsung dari link Google Spreadsheet');

Artisan::command('agenda:remind-telegram {--force : Paksa kirim meskipun sudah pernah dikirim hari ini}', function (TelegramService $telegramService) {
    $this->info('Memeriksa agenda kegiatan H-1 untuk notifikasi Telegram...');
    $force = (bool) $this->option('force');
    $result = $telegramService->sendAgendaReminderHMinus1($force);

    if ($result['sent'] ?? false) {
        $this->info($result['message']);
    } else {
        $this->comment($result['message']);
    }
})->purpose('Mengirimkan notifikasi pengingat agenda kegiatan H-1 sesuai kalender ke bot Telegram');

// Jadwal Cron Otomatis
Schedule::command('absensi:close-daily')->dailyAt('17:00');

// Jadwal Pengingat Kegiatan H-1 ke Telegram sesuai jam di Pengaturan (Default: 07:00 WIB)
Schedule::command('agenda:remind-telegram')
    ->everyMinute()
    ->when(function () {
        $now = Carbon::now('Asia/Jakarta');
        $jamTarget = Pengaturan::getValue('telegram_notif_agenda_jam', '07:00');
        $cleanJam = substr(trim($jamTarget ?: '07:00'), 0, 5);

        return $now->format('H:i') === $cleanJam;
    });
