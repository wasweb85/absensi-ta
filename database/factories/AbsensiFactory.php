<?php

namespace Database\Factories;

use App\Models\Absensi;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Absensi>
 */
class AbsensiFactory extends Factory
{
    protected $model = Absensi::class;

    public function definition(): array
    {
        return [
            'id_siswa' => Siswa::factory(),
            'tanggal' => now()->toDateString(),
            'waktu_masuk' => '07:15:00',
            'waktu_pulang' => '14:00:00',
            'status' => 'Hadir',
            'metode_scan' => 'qr_kamera',
            'keterangan' => null,
        ];
    }

    public function hadir(): static
    {
        return $this->state(fn () => [
            'status' => 'Hadir',
            'waktu_masuk' => '07:15:00',
            'waktu_pulang' => '14:00:00',
        ]);
    }

    public function izin(?string $keterangan = 'Izin keluarga'): static
    {
        return $this->state(fn () => [
            'status' => 'Izin',
            'waktu_masuk' => null,
            'waktu_pulang' => null,
            'keterangan' => $keterangan,
        ]);
    }

    public function sakit(?string $keterangan = 'Sakit demam'): static
    {
        return $this->state(fn () => [
            'status' => 'Sakit',
            'waktu_masuk' => null,
            'waktu_pulang' => null,
            'keterangan' => $keterangan,
        ]);
    }

    public function alpa(): static
    {
        return $this->state(fn () => [
            'status' => 'Alpa',
            'waktu_masuk' => null,
            'waktu_pulang' => null,
            'keterangan' => 'Tidak hadir tanpa keterangan',
        ]);
    }
}
