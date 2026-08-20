<?php

declare(strict_types=1);

namespace App\Support\Impor;

/**
 * Hasil pembacaan satu berkas impor.
 *
 * `galatBerkas` terisi bila berkasnya sendiri yang bermasalah — kosong, salah
 * kolom, atau kelewat banyak baris. Dalam keadaan itu `baris` kosong dan tidak
 * ada yang bisa ditawarkan untuk disimpan.
 */
final readonly class HasilImpor
{
    /**
     * @param  list<BarisImpor>  $baris
     */
    public function __construct(
        public array $baris = [],
        public ?string $galatBerkas = null,
    ) {}

    public static function gagal(string $pesan): self
    {
        return new self(galatBerkas: $pesan);
    }

    public function berkasBermasalah(): bool
    {
        return $this->galatBerkas !== null;
    }

    public function kosong(): bool
    {
        return $this->baris === [];
    }

    /**
     * @return list<BarisImpor>
     */
    public function barisSah(): array
    {
        return array_values(array_filter($this->baris, static fn (BarisImpor $b): bool => $b->sah()));
    }

    /**
     * @return list<BarisImpor>
     */
    public function barisGalat(): array
    {
        return array_values(array_filter($this->baris, static fn (BarisImpor $b): bool => ! $b->sah()));
    }

    public function jumlahTambah(): int
    {
        return count(array_filter(
            $this->barisSah(),
            static fn (BarisImpor $b): bool => $b->aksi() === BarisImpor::TAMBAH,
        ));
    }

    public function jumlahPerbarui(): int
    {
        return count(array_filter(
            $this->barisSah(),
            static fn (BarisImpor $b): bool => $b->aksi() === BarisImpor::PERBARUI,
        ));
    }

    public function jumlahGalat(): int
    {
        return count($this->barisGalat());
    }

    /**
     * Impor hanya boleh dijalankan bila seluruh baris bersih. Sebagian masuk
     * sebagian gagal akan menyulitkan operator melacak sisa pekerjaannya.
     */
    public function siapDisimpan(): bool
    {
        return ! $this->berkasBermasalah() && ! $this->kosong() && $this->jumlahGalat() === 0;
    }
}
