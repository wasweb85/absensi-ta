<?php

namespace Database\Factories;

use App\Models\Kelas;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kelas>
 */
class KelasFactory extends Factory
{
    protected $model = Kelas::class;

    public function definition(): array
    {
        return [
            'nama_kelas' => 'Jilid '.fake()->unique()->numberBetween(1, 20),
            'wali_kelas' => fake()->name(),
            'no_hp_wali' => fake()->numerify('08##########'),
            'warna_label' => fake()->hexColor(),
            'urutan' => fake()->numberBetween(1, 10),
        ];
    }
}
