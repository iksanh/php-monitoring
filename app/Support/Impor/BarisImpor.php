<?php

declare(strict_types=1);

namespace App\Support\Impor;

/**
 * Satu baris berkas impor setelah dibaca dan divalidasi.
 *
 * Baris yang bergalat tetap disimpan di hasil — halaman pratinjau perlu
 * menampilkannya beserta nomor baris asli di Excel supaya operator tahu
 * persis baris mana yang harus dibetulkan.
 */
final readonly class BarisImpor
{
    public const TAMBAH = 'tambah';

    public const PERBARUI = 'perbarui';

    /**
     * @param  int  $nomorBaris  Nomor baris pada berkas Excel, termasuk header.
     * @param  array<string, mixed>  $nilai  Siap dipakai Bidang::create()/update().
     * @param  list<string>  $galat
     * @param  int|null  $bidangId  Bidang lama yang akan diperbarui, bila ada.
     */
    public function __construct(
        public int $nomorBaris,
        public array $nilai,
        public array $galat,
        public ?int $bidangId,
        public string $nomorUrut,
        public string $namaAset,
        public string $instansi,
    ) {}

    public function sah(): bool
    {
        return $this->galat === [];
    }

    public function aksi(): string
    {
        return $this->bidangId === null ? self::TAMBAH : self::PERBARUI;
    }
}
