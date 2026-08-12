<?php

declare(strict_types=1);

namespace App\Support\Dashboard;

/**
 * Angka ringkas untuk target satu tahun.
 */
final readonly class KartuAngka
{
    public function __construct(
        public int $tahun,
        public int $total,
        public int $bersertipikat,
        public int $proses,
        public int $terkendala,
        public int $diserahkan = 0,
    ) {}

    public function persenCapaian(): int
    {
        if ($this->total === 0) {
            return 0;
        }

        return (int) round($this->bersertipikat / $this->total * 100);
    }
}
