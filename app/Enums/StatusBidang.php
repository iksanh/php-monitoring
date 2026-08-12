<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status bidang, diisi manual oleh operator. Bukan hasil turunan tanggal —
 * aplikasi ini papan pemantauan manual, bukan state machine.
 */
enum StatusBidang: string
{
    case Proses = 'proses';
    case Selesai = 'selesai';
    case Diserahkan = 'diserahkan';
    case Terkendala = 'terkendala';

    public function label(): string
    {
        return match ($this) {
            self::Proses => 'Dalam proses',
            self::Selesai => 'Selesai',
            self::Diserahkan => 'Sudah diserahkan',
            self::Terkendala => 'Terkendala',
        };
    }

    /**
     * Status yang berarti berkas tidak lagi berjalan.
     *
     * @return list<self>
     */
    public static function tuntas(): array
    {
        return [self::Selesai, self::Diserahkan];
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
