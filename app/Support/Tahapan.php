<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Pembaca config('tahapan').
 *
 * Semua bagian aplikasi yang butuh daftar tahap memanggil kelas ini, bukan
 * menulis nama tahap sendiri.
 */
final class Tahapan
{
    /**
     * Seluruh tahap sesuai urutan config.
     *
     * @return list<Tahap>
     */
    public static function semua(): array
    {
        $config = config('tahapan');

        if (! is_array($config) || $config === []) {
            throw new InvalidArgumentException('config/tahapan.php kosong atau bukan array.');
        }

        $tahapan = [];
        $urutan = 0;

        foreach ($config as $definisi) {
            if (! is_array($definisi)) {
                throw new InvalidArgumentException('Setiap elemen config/tahapan.php harus berupa array.');
            }

            /** @var array<string, mixed> $definisi */
            $tahapan[] = Tahap::dariConfig(++$urutan, $definisi);
        }

        return $tahapan;
    }

    /**
     * Kolom tanggal kedelapan tahap, dipakai untuk query agregasi dan export.
     *
     * @return list<string>
     */
    public static function kolomTanggal(): array
    {
        return array_map(static fn (Tahap $tahap): string => $tahap->kolom, self::semua());
    }

    public static function cari(string $kolom): ?Tahap
    {
        foreach (self::semua() as $tahap) {
            if ($tahap->kolom === $kolom) {
                return $tahap;
            }
        }

        return null;
    }

    public static function pertama(): Tahap
    {
        return self::semua()[0];
    }

    public static function jumlah(): int
    {
        return count(self::semua());
    }
}
