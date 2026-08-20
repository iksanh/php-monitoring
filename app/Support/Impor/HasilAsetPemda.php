<?php

declare(strict_types=1);

namespace App\Support\Impor;

/**
 * Hasil pembacaan berkas daftar aset tanah pemda.
 *
 * Terpisah dari App\Support\Impor\HasilImpor karena berbeda tujuan: yang itu
 * melayani pratinjau di layar dengan galat per baris, yang ini melayani
 * perintah konsol yang memuat ribuan baris sekaligus dan hanya perlu
 * ringkasan.
 */
final readonly class HasilAsetPemda
{
    /**
     * @param  list<array<string, mixed>>  $bidang  Atribut siap simpan, belum
     *                                              berisi `instansi_id`.
     * @param  array<string, int>  $perLembar  Jumlah baris terbaca per sheet.
     * @param  list<string>  $catatan  Kejanggalan yang perlu dilihat manusia.
     */
    public function __construct(
        public array $bidang,
        public array $perLembar = [],
        public int $dilewati = 0,
        public array $catatan = [],
    ) {}

    public function jumlah(): int
    {
        return count($this->bidang);
    }

    /**
     * Bidang yang sertipikatnya sudah terbit menurut berkas sumber.
     */
    public function bersertipikat(): int
    {
        return count(array_filter(
            $this->bidang,
            static fn (array $bidang): bool => ($bidang['tgl_sertipikat'] ?? null) !== null,
        ));
    }
}
