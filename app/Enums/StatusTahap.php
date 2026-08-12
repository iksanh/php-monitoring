<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * PENINGGALAN. Tahap kondisional sudah dihapus dari spec — seluruh tahap kini
 * wajib — dan kolom `pengumuman_status` serta `kewajiban_status` dibuang oleh
 * migrasi 2026_08_12_140000.
 *
 * Enum ini hanya disisakan karena migrasi 2026_08_12_100100 menyebutnya, dan
 * migrasi lama tidak boleh diubah. Jangan dipakai kode baru.
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
