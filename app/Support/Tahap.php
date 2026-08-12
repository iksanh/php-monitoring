<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PenanggungJawab;
use InvalidArgumentException;

/**
 * Satu elemen config('tahapan') dalam bentuk objek.
 *
 * Bukan sumber data — hanya pembungkus supaya config dapat dibaca terketik
 * (`$tahap->label`) di controller, service, dan Blade. Satu-satunya sumber
 * nama, urutan, dan unit tahap tetap `config/tahapan.php`.
 */
final readonly class Tahap
{
    /**
     * Peninggalan skema lama: selalu null sejak tahap kondisional dihapus.
     *
     * Dipertahankan semata karena migrasi
     * `2026_08_12_100100_create_bidang_table` membaca config saat dijalankan
     * dan menyentuh properti ini. Jangan dipakai kode baru.
     */
    public ?string $kolomStatus;

    public function __construct(
        public int $urutan,
        public string $kolom,
        public string $label,
        public string $labelMenunggu,
        public string $unit,
        public PenanggungJawab $penanggungJawab,
        public string $dokumen,
    ) {
        $this->kolomStatus = null;
    }

    /**
     * @param  array<string, mixed>  $definisi
     */
    public static function dariConfig(int $urutan, array $definisi): self
    {
        return new self(
            urutan: $urutan,
            kolom: self::teks($definisi, 'kolom'),
            label: self::teks($definisi, 'label'),
            labelMenunggu: self::teks($definisi, 'label_menunggu'),
            unit: self::teks($definisi, 'unit'),
            penanggungJawab: PenanggungJawab::from(self::teks($definisi, 'penanggung_jawab')),
            dokumen: self::teks($definisi, 'dokumen'),
        );
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
