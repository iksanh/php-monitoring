<?php

declare(strict_types=1);

namespace App\Support\Dashboard;

use App\Support\Tahap;

/**
 * Jumlah bidang yang sedang menunggu satu tahap tertentu, yaitu bidang yang
 * tahap berikutnya adalah tahap ini.
 */
final readonly class TahapTertahan
{
    public function __construct(
        public Tahap $tahap,
        public int $jumlah,
    ) {}
}
