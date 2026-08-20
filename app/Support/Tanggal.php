<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
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

    /**
     * Kebalikan pendek()/panjang(): membaca tanggal yang diketik manusia.
     *
     * Dipakai impor Excel, tempat sel tanggal sering berisi teks — baik
     * ketikan operator (`12/08/2026`) maupun keluaran pendek() sendiri
     * (`12 Agu 2026`), sehingga berkas hasil export bisa diunggah kembali.
     *
     * Hanya format yang tidak mendua yang diterima. `12/08/2026` selalu
     * dibaca 12 Agustus, tidak pernah 8 Desember, karena penulisan
     * hari-bulan-tahun itulah yang berlaku di seluruh aplikasi ini.
     */
    public static function dariTeks(string $teks): ?string
    {
        $bersih = trim(preg_replace('/\s+/', ' ', $teks) ?? $teks);

        if ($bersih === '') {
            return null;
        }

        $bersih = self::gantiNamaBulan($bersih);

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'd m Y'] as $format) {
            $tanggal = DateTimeImmutable::createFromFormat('!'.$format, $bersih);

            // createFromFormat menerima 31/02/2026 lalu menggesernya diam-diam
            // ke 3 Maret; kejanggalan itu hanya muncul sebagai warning. Baris
            // sejumlah satu digit seperti 1/6/2026 tetap sah.
            if ($tanggal !== false && DateTimeImmutable::getLastErrors() === false) {
                return $tanggal->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * Menukar nama bulan Indonesia menjadi angka, supaya sisa penguraian
     * cukup menangani format berangka saja.
     */
    private static function gantiNamaBulan(string $teks): string
    {
        foreach ([self::BULAN_PANJANG, self::BULAN_PENDEK] as $daftar) {
            foreach ($daftar as $nomor => $nama) {
                $pola = '/(?<=^|[\s\-.\/])'.preg_quote($nama, '/').'(?=$|[\s\-.\/])/iu';
                $hasil = preg_replace($pola, str_pad((string) $nomor, 2, '0', STR_PAD_LEFT), $teks);

                if ($hasil !== null && $hasil !== $teks) {
                    return $hasil;
                }
            }
        }

        return $teks;
    }
}
