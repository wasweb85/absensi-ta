<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'admin',
            'id_kelas' => null,
            'nisn' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'role' => 'admin',
        ]);
    }

    public function bendahara(): static
    {
        return $this->state(fn () => [
            'role' => 'bendahara',
        ]);
    }

    public function guru(?int $idKelas = null): static
    {
        return $this->state(fn () => [
            'role' => 'guru',
            'id_kelas' => $idKelas,
        ]);
    }

    public function kepala(): static
    {
        return $this->state(fn () => [
            'role' => 'kepala',
        ]);
    }

    public function siswa(?string $nisn = null): static
    {
        return $this->state(fn () => [
            'role' => 'siswa',
            'nisn' => $nisn,
        ]);
    }
}
