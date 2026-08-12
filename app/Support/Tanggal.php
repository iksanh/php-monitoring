<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeInterface;

/**
 * Pemformat tanggal tampilan.
 *
 * Nama bulan ditulis sendiri, tidak lewat IntlDateFormatter, supaya tidak
 * bergantung pada ekstensi `intl` yang belum tentu aktif di shared hosting.
 */
final class Tanggal
{
    public const KOSONG = '—';

    /**
     * @var array<int, string>
     */
    private const BULAN_PENDEK = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];

    /**
     * @var array<int, string>
     */
    private const BULAN_PANJANG = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /**
     * Format `d M Y` dengan bulan Indonesia, mis. "12 Agu 2026".
     */
    public static function pendek(?DateTimeInterface $tanggal): string
    {
        if ($tanggal === null) {
            return self::KOSONG;
        }

        return $tanggal->format('d').' '.self::BULAN_PENDEK[(int) $tanggal->format('n')].' '.$tanggal->format('Y');
    }

    /**
     * Nama bulan penuh, mis. "12 Agustus 2026".
     */
    public static function panjang(?DateTimeInterface $tanggal): string
    {
        if ($tanggal === null) {
            return self::KOSONG;
        }

        return $tanggal->format('d').' '.self::BULAN_PANJANG[(int) $tanggal->format('n')].' '.$tanggal->format('Y');
    }

    /**
     * Tanggal beserta jam, mis. "12 Agu 2026 09.30".
     */
    public static function waktu(?DateTimeInterface $tanggal): string
    {
        if ($tanggal === null) {
            return self::KOSONG;
        }

        return self::pendek($tanggal).' '.$tanggal->format('H.i');
    }

    /**
     * Nilai untuk atribut `value` input type=date.
     */
    public static function input(?DateTimeInterface $tanggal): string
    {
        return $tanggal?->format('Y-m-d') ?? '';
    }
}
