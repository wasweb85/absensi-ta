<?php

namespace Database\Factories;

use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Siswa>
 */
class SiswaFactory extends Factory
{
    protected $model = Siswa::class;

    public function definition(): array
    {
        return [
            'nisn' => fake()->unique()->numerify('##########'),
            'nama_lengkap' => fake()->name(),
            'jenis_kelamin' => fake()->randomElement(['L', 'P']),
            'id_kelas' => Kelas::factory(),
            'no_hp_ortu' => fake()->numerify('08##########'),
            'nama_ortu' => fake()->name(),
            'alamat' => fake()->address(),
            'status' => 'aktif',
            'foto' => null,
        ];
    }

    public function lakiLaki(): static
    {
        return $this->state(fn () => [
            'jenis_kelamin' => 'L',
        ]);
    }

    public function perempuan(): static
    {
        return $this->state(fn () => [
            'jenis_kelamin' => 'P',
        ]);
    }

    public function lulus(): static
    {
        return $this->state(fn () => [
            'status' => 'lulus',
        ]);
    }

    public function keluar(): static
    {
        return $this->state(fn () => [
            'status' => 'keluar',
        ]);
    }
}
