<?php

namespace Database\Factories;

use App\Models\Agenda;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agenda>
 */
class AgendaFactory extends Factory
{
    protected $model = Agenda::class;

    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('now', '+1 month')->format('Y-m-d');

        return [
            'tanggal' => $startDate,
            'tanggal_selesai' => $startDate,
            'judul' => fake()->sentence(3),
            'kategori' => fake()->randomElement(['umum', 'rapat', 'ujian', 'kegiatan']),
            'keterangan' => fake()->paragraph(1),
        ];
    }
}
