<?php

namespace Database\Factories;

use App\Models\PembayaranSpp;
use App\Models\Siswa;
use App\Models\TagihanSpp;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PembayaranSpp>
 */
class PembayaranSppFactory extends Factory
{
    protected $model = PembayaranSpp::class;

    public function definition(): array
    {
        return [
            'id_tagihan' => TagihanSpp::factory(),
            'id_siswa' => fn (array $attributes) => TagihanSpp::find($attributes['id_tagihan'])?->id_siswa ?? Siswa::factory(),
            'nomor_kuitansi' => 'KW-'.now()->format('Ymd').'-'.fake()->unique()->numerify('####'),
            'tanggal_bayar' => now(),
            'jumlah_bayar' => 15000.00,
            'metode_pembayaran' => 'Tunai',
            'id_petugas' => User::factory(),
            'status_transaksi' => 'valid',
            'alasan_pembatalan' => null,
            'dibatalkan_oleh' => null,
            'catatan' => null,
        ];
    }

    public function transfer(): static
    {
        return $this->state(fn () => [
            'metode_pembayaran' => 'Transfer',
        ]);
    }

    public function dibatalkan(): static
    {
        return $this->state(fn () => [
            'status_transaksi' => 'dibatalkan',
            'alasan_pembatalan' => 'Salah input nominal',
            'dibatalkan_oleh' => 'Petugas Keuangan',
        ]);
    }
}
