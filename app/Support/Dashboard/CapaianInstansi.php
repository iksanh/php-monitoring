<?php

declare(strict_types=1);

namespace App\Support\Dashboard;

final readonly class CapaianInstansi
{
    public function __construct(
        public string $nama,
        public int $total,
        public int $bersertipikat,
    ) {}

    public function persen(): int
    {
        if ($this->total === 0) {
            return 0;
        }

        return (int) round($this->bersertipikat / $this->total * 100);
    }
}
