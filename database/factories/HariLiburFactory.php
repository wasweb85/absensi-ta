<?php

namespace Database\Factories;

use App\Models\HariLibur;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HariLibur>
 */
class HariLiburFactory extends Factory
{
    protected $model = HariLibur::class;

    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('now', '+1 month')->format('Y-m-d');

        return [
            'tanggal_mulai' => $startDate,
            'tanggal_selesai' => $startDate,
            'keterangan' => fake()->sentence(3),
        ];
    }
}
