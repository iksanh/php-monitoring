<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PenanggungJawab;
use App\Enums\SifatTahap;
use InvalidArgumentException;

/**
 * Satu elemen config('tahapan') dalam bentuk objek.
 *
 * Bukan sumber data — hanya pembungkus supaya config dapat dibaca terketik
 * (`$tahap->label`) di controller, service, dan Blade. Satu-satunya sumber
 * nama, urutan, unit, dan sifat tahap tetap `config/tahapan.php`.
 */
final readonly class Tahap
{
    public function __construct(
        public int $urutan,
        public string $kolom,
        public string $label,
        public string $unit,
        public PenanggungJawab $penanggungJawab,
        public string $dokumen,
        public SifatTahap $sifat,
        public ?string $kolomStatus,
    ) {}

    /**
     * @param  array<string, mixed>  $definisi
     */
    public static function dariConfig(int $urutan, array $definisi): self
    {
        $sifat = SifatTahap::from(self::teks($definisi, 'sifat'));

        $kolomStatus = $definisi['kolom_status'] ?? null;

        if ($sifat === SifatTahap::Kondisional && ! is_string($kolomStatus)) {
            throw new InvalidArgumentException(
                "Tahap kondisional [{$definisi['kolom']}] wajib punya kolom_status pada config/tahapan.php."
            );
        }

        return new self(
            urutan: $urutan,
            kolom: self::teks($definisi, 'kolom'),
            label: self::teks($definisi, 'label'),
            unit: self::teks($definisi, 'unit'),
            penanggungJawab: PenanggungJawab::from(self::teks($definisi, 'penanggung_jawab')),
            dokumen: self::teks($definisi, 'dokumen'),
            sifat: $sifat,
            kolomStatus: is_string($kolomStatus) ? $kolomStatus : null,
        );
    }

    public function kondisional(): bool
    {
        return $this->sifat === SifatTahap::Kondisional;
    }

    /**
     * @param  array<string, mixed>  $definisi
     */
    private static function teks(array $definisi, string $key): string
    {
        $nilai = $definisi[$key] ?? null;

        if (! is_string($nilai) || $nilai === '') {
            throw new InvalidArgumentException(
                "Key [{$key}] pada config/tahapan.php harus berupa teks tidak kosong."
            );
        }

        return $nilai;
    }
}
