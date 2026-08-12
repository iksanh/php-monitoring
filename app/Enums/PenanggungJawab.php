<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Pihak yang memegang bola pada suatu tahap.
 *
 * Nilai diambil dari key `penanggung_jawab` pada config('tahapan'). Pemisahan
 * ini yang membuat grafik donat dashboard bermakna: tanpa itu Kantah selalu
 * terlihat sebagai penyebab keterlambatan padahal sebagian bola ada di pemohon.
 */
enum PenanggungJawab: string
{
    case Kantah = 'kantah';
    case Pemohon = 'pemohon';

    public function label(): string
    {
        return match ($this) {
            self::Kantah => 'Kantor Pertanahan',
            self::Pemohon => 'Pemohon',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function pilihan(): array
    {
        $pilihan = [];

        foreach (self::cases() as $case) {
            $pilihan[$case->value] = $case->label();
        }

        return $pilihan;
    }
}
