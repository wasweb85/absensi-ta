<?php

namespace Database\Factories;

use App\Models\WhatsAppLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppLog>
 */
class WhatsAppLogFactory extends Factory
{
    protected $model = WhatsAppLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fonnte_id' => 'msg_'.fake()->unique()->numerify('######'),
            'nomor_tujuan' => '628'.fake()->numerify('##########'),
            'nama_penerima' => fake()->name(),
            'pesan' => fake()->sentence(10),
            'tipe_pesan' => fake()->randomElement(['presensi', 'broadcast', 'chatbot_reply', 'manual']),
            'status' => 'pending',
            'keterangan_error' => null,
            'retry_count' => 0,
            'last_retried_at' => null,
            'sent_at' => null,
            'delivered_at' => null,
            'read_at' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'sent',
            'sent_at' => Carbon::now('Asia/Jakarta'),
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'delivered',
            'sent_at' => Carbon::now('Asia/Jakarta')->subSeconds(5),
            'delivered_at' => Carbon::now('Asia/Jakarta'),
        ]);
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'read',
            'sent_at' => Carbon::now('Asia/Jakarta')->subMinutes(5),
            'delivered_at' => Carbon::now('Asia/Jakarta')->subMinutes(4),
            'read_at' => Carbon::now('Asia/Jakarta'),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'keterangan_error' => 'Device disconnected or invalid number',
        ]);
    }
}
