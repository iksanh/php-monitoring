<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Bidang;
use App\Support\Filter\FilterBidang;
use App\Support\Tahap;
use App\Support\Tahapan;
use App\Support\Tanggal;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export daftar bidang sesuai filter aktif.
 *
 * Kolom tanggal tahap mengikuti urutan config('tahapan') — menambah atau
 * mengganti nama tahap di config langsung terbawa ke berkas Excel.
 *
 * @implements WithMapping<Bidang>
 */
class BidangExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private readonly FilterBidang $filter) {}

    /**
     * @return Builder<Bidang>
     */
    public function query(): Builder
    {
        return $this->filter->terapkan(
            Bidang::query()->with('instansi')->orderBy('nomor_urut')
        );
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        $judul = [
            'Nomor urut',
            'Nama aset',
            'Instansi pemilik',
            'Penggunaan',
            'Desa',
            'Kecamatan',
            'Luas (m2)',
            'Nomor berkas KKP',
            'Tahun target',
        ];

        foreach (Tahapan::semua() as $tahap) {
            $judul[] = $tahap->urutan.'. '.$tahap->label;
        }

        return array_merge($judul, [
            'Tahap aktif',
            'Tahap berikut',
            'Penanggung jawab',
            'Umur (hari)',
            'Progres (%)',
            'Status',
            'Keterangan',
        ]);
    }

    /**
     * @param  Bidang  $row
     * @return list<string|int|float|null>
     */
    public function map($row): array
    {
        $baris = [
            $row->nomor_urut,
            $row->nama_aset,
            $row->instansi->nama,
            $row->penggunaan,
            $row->desa,
            $row->kecamatan,
            $row->luas_m2 !== null ? (float) $row->luas_m2 : null,
            $row->nomor_berkas_kkp,
            $row->tahun_target,
        ];

        foreach (Tahapan::semua() as $tahap) {
            $baris[] = $this->tanggalTahap($row, $tahap);
        }

        $aktif = $row->tahapAktif;
        $berikut = $row->tahapBerikut;

        return array_merge($baris, [
            $aktif !== null ? $aktif->label : 'Belum mulai',
            $berikut !== null ? $berikut->label : 'Tuntas',
            $row->penanggungJawab?->label() ?? '-',
            $row->umurHari,
            $row->persenProgres,
            $row->status->label(),
            $row->keterangan,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    /**
     * Tahap yang tidak berlaku ditandai jelas, bukan dibiarkan kosong — kolom
     * kosong akan terbaca sebagai "belum dikerjakan".
     */
    private function tanggalTahap(Bidang $bidang, Tahap $tahap): string
    {
        if (! $bidang->tahapDipakai($tahap)) {
            return 'tidak berlaku';
        }

        $tanggal = $bidang->tanggalTahap($tahap);

        return $tanggal !== null ? Tanggal::pendek($tanggal) : '';
    }
}
