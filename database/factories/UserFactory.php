<?php

namespace Database\Factories;

use App\Enums\Peran;
use App\Models\Instansi;
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
            'email' => fake()->unique()->safeEmail(),
            'instansi_id' => null,
            'role' => Peran::Viewer,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Peran pengguna. Penegakan haknya lewat Gate/Policy pada TAHAP B.
     */
    public function peran(Peran $peran): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => $peran,
        ]);
    }

    public function admin(): static
    {
        return $this->peran(Peran::Admin);
    }

    public function operator(): static
    {
        return $this->peran(Peran::Operator);
    }

    public function viewer(): static
    {
        return $this->peran(Peran::Viewer);
    }

    /**
     * @param  Instansi|Factory<Instansi>  $instansi
     */
    public function dariInstansi(Instansi|Factory $instansi): static
    {
        return $this->state(fn (array $attributes) => [
            'instansi_id' => $instansi instanceof Instansi ? $instansi->id : $instansi,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
