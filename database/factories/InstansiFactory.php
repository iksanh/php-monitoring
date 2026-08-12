<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Instansi;
use App\Models\JenisInstansi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Instansi>
 */
class InstansiFactory extends Factory
{
    protected $model = Instansi::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => 'Dinas '.fake()->randomElement([
                'Pendidikan', 'Kesehatan', 'Pekerjaan Umum', 'Sosial', 'Pertanian',
            ]).' '.fake()->unique()->numberBetween(1, 9999),
            // Dipakai ulang supaya tidak lahir satu jenis baru per instansi.
            'jenis_instansi_id' => fn (): int => JenisInstansiFactory::bawaan()->id,
            'aktif' => true,
        ];
    }

    public function jenis(JenisInstansi $jenis): static
    {
        return $this->state(fn (): array => ['jenis_instansi_id' => $jenis->id]);
    }

    public function nonaktif(): static
    {
        return $this->state(fn (): array => ['aktif' => false]);
    }
}
