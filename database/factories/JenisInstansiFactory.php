<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\JenisInstansi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JenisInstansi>
 */
class JenisInstansiFactory extends Factory
{
    protected $model = JenisInstansi::class;

    private static int $urut = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nama = 'Jenis Uji '.(++self::$urut);

        return [
            'kode' => 'jenis_uji_'.self::$urut,
            'nama' => $nama,
            'aktif' => true,
        ];
    }

    public function nonaktif(): static
    {
        return $this->state(fn (): array => ['aktif' => false]);
    }

    /**
     * Jenis bawaan yang dipakai kode aplikasi, dibuat sekali lalu dipakai ulang.
     */
    public static function bawaan(string $kode = 'pemda', string $nama = 'Pemerintah Daerah'): JenisInstansi
    {
        return JenisInstansi::query()->firstOrCreate(
            ['kode' => $kode],
            ['nama' => $nama, 'aktif' => true],
        );
    }
}
