<?php

declare(strict_types=1);

namespace App\Exports\Template;

use App\Support\Impor\KolomImpor;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Lembar isian template impor: baris judul saja, tanpa contoh data.
 *
 * Contoh data sengaja tidak disertakan — operator cenderung lupa
 * menghapusnya, dan barisnya akan ikut terimpor sebagai bidang palsu.
 * Penjelasan tiap kolom ditaruh di LembarPetunjuk.
 */
class LembarBidang implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    /**
     * Banyak baris yang sudah disiapkan berformat tanggal, supaya tanggal
     * yang diketik operator langsung menjadi sel tanggal betulan.
     */
    private const BARIS_SIAP = 300;

    public function title(): string
    {
        return 'Data Bidang';
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return KolomImpor::judul();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');

        foreach (KolomImpor::semua() as $nomor => $kolom) {
            $huruf = Coordinate::stringFromColumnIndex($nomor + 1);

            $sheet->getStyle($huruf.'2:'.$huruf.self::BARIS_SIAP)
                ->getNumberFormat()
                ->setFormatCode(match ($kolom->jenis) {
                    KolomImpor::TANGGAL => 'DD/MM/YYYY',
                    KolomImpor::ANGKA => '#,##0.00',
                    KolomImpor::TAHUN => '0',
                    // Nomor urut kerap berbentuk "007" atau "12/2026"; sebagai
                    // teks ia tidak diubah Excel menjadi angka atau tanggal.
                    default => '@',
                });
        }

        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F4F4F5']],
            ],
        ];
    }
}
