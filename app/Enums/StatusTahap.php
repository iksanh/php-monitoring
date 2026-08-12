<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Nilai kolom pendamping tahap kondisional (`pengumuman_status`,
 * `kewajiban_status`).
 *
 * `TidakBerlaku` berarti tahap dilewati dalam perhitungan tahap aktif, tidak
 * dihitung sebagai bidang tertahan, dan tidak masuk penyebut persentase.
 */
enum StatusTahap: string
{
    case Berlaku = 'berlaku';
    case TidakBerlaku = 'tidak_berlaku';

    public function label(): string
    {
        return match ($this) {
            self::Berlaku => 'Berlaku',
            self::TidakBerlaku => 'Tidak berlaku',
        };
    }

    public function berlaku(): bool
    {
        return $this === self::Berlaku;
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
