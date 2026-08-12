<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Bidang;
use App\Models\Kendala;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kendala>
 */
class KendalaFactory extends Factory
{
    protected $model = Kendala::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bidang_id' => Bidang::factory(),
            'uraian' => fake()->randomElement([
                'Batas bidang belum disepakati dengan pemilik tanah bersebelahan.',
                'Dokumen alas hak belum lengkap, menunggu salinan dari bagian aset.',
                'Objek tumpang tindih dengan bidang terdaftar, perlu pengecekan peta.',
                'Berkas belum diinput ke KKP karena antrean loket.',
                'Pemohon belum menyampaikan bukti pemenuhan kewajiban.',
                'Tanah masih dikuasai pihak ketiga, perlu koordinasi dengan Kejaksaan.',
                'Menunggu jadwal sidang panitia pemeriksa tanah.',
            ]),
            'tanggal_catat' => CarbonImmutable::now()->subDays(fake()->numberBetween(10, 300)),
            'tanggal_selesai' => null,
            'dicatat_oleh' => fake()->randomElement([
                'Andi Prasetyo', 'Siti Rahmawati', 'Bayu Nugroho', 'Dewi Lestari', 'Rudi Hartono',
            ]),
        ];
    }

    /**
     * Kendala yang sudah ditutup.
     */
    public function selesai(): static
    {
        return $this->state(function (array $attributes): array {
            $catat = $attributes['tanggal_catat'] ?? null;

            $mulai = $catat instanceof DateTimeInterface
                ? CarbonImmutable::instance($catat)
                : CarbonImmutable::now()->subDays(60);

            $selesai = $mulai->addDays(fake()->numberBetween(5, 90));

            return [
                'tanggal_selesai' => $selesai->isFuture() ? CarbonImmutable::now() : $selesai,
            ];
        });
    }
}
