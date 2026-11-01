<?php

namespace Database\Factories;

use App\Models\Siswa;
use App\Models\TagihanSpp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TagihanSpp>
 */
class TagihanSppFactory extends Factory
{
    protected $model = TagihanSpp::class;

    public function definition(): array
    {
        $year = (int) date('Y');

        return [
            'id_siswa' => Siswa::factory(),
            'bulan' => fake()->numberBetween(1, 12),
            'tahun' => $year,
            'tahun_ajaran' => "{$year}/".($year + 1),
            'nominal_tagihan' => 15000.00,
            'status' => 'belum_lunas',
            'jatuh_tempo' => now()->addDays(10)->toDateString(),
        ];
    }

    public function lunas(): static
    {
        return $this->state(fn () => [
            'status' => 'lunas',
        ]);
    }

    public function kurangBayar(): static
    {
        return $this->state(fn () => [
            'status' => 'kurang_bayar',
        ]);
    }

    public function belumLunas(): static
    {
        return $this->state(fn () => [
            'status' => 'belum_lunas',
        ]);
    }
}

