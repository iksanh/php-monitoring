<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\KategoriKendala;
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
        $kategori = fake()->randomElement(KategoriKendala::cases());

        return [
            'bidang_id' => Bidang::factory(),
            'kategori' => $kategori,
            'uraian' => $this->uraian($kategori),
            'tanggal_catat' => CarbonImmutable::now()->subDays(fake()->numberBetween(10, 300)),
            'tanggal_selesai' => null,
            'dicatat_oleh' => fake()->randomElement([
                'Andi Prasetyo', 'Siti Rahmawati', 'Bayu Nugroho', 'Dewi Lestari', 'Rudi Hartono',
            ]),
        ];
    }

    public function kategori(KategoriKendala $kategori): static
    {
        return $this->state(fn (): array => [
            'kategori' => $kategori,
            'uraian' => $this->uraian($kategori),
        ]);
    }

    /**
     * Uraian yang cocok dengan kategorinya, supaya data contoh tidak
     * bertentangan sendiri.
     */
    private function uraian(KategoriKendala $kategori): string
    {
        return match ($kategori) {
            KategoriKendala::BerkasKurang => 'Dokumen alas hak belum lengkap, menunggu salinan dari bagian aset.',
            KategoriKendala::MenungguPemohon => 'Pemohon belum menyampaikan kelengkapan yang diminta petugas.',
            KategoriKendala::Sengketa => 'Tanah masih dikuasai pihak ketiga, perlu koordinasi dengan Kejaksaan.',
            KategoriKendala::OverlapBidang => 'Objek tumpang tindih dengan bidang terdaftar, perlu pengecekan peta.',
            KategoriKendala::KawasanHutan => 'Objek terindikasi masuk kawasan hutan, menunggu hasil telaah.',
            KategoriKendala::Lainnya => 'Batas bidang belum disepakati dengan pemilik tanah bersebelahan.',
        };
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
