<?php

declare(strict_types=1);

namespace App\Support\Dashboard;

/**
 * Ringkasan daftar aset di belakang papan pantau.
 *
 * Angka-angka ini sengaja dipisah dari KartuAngka: kartu itu menghitung
 * pekerjaan Kantor Pertanahan pada satu tahun target, sedangkan ini
 * menggambarkan seluruh aset pemda — termasuk yang belum pernah disentuh dan
 * yang sertipikatnya sudah terbit jauh sebelum papan pantau ini ada.
 */
final readonly class RingkasAset
{
    public function __construct(
        public int $total,
        public int $dipantau,
        public int $antrean,
        public int $riwayatBersertipikat,
    ) {}

    /**
     * Bagian aset yang sudah masuk pantauan, untuk kalimat pengantar.
     */
    public function persenDipantau(): int
    {
        if ($this->total === 0) {
            return 0;
        }

        return (int) round($this->dipantau / $this->total * 100);
    }
}
