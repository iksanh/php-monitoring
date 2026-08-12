<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Sifat tahap sesuai key `sifat` pada config('tahapan').
 *
 * Tahap kondisional punya kolom status pendamping dan dapat dinyatakan tidak
 * berlaku untuk suatu bidang.
 */
enum SifatTahap: string
{
    case Wajib = 'wajib';
    case Kondisional = 'kondisional';

    public function label(): string
    {
        return match ($this) {
            self::Wajib => 'Wajib',
            self::Kondisional => 'Kondisional',
        };
    }
}
