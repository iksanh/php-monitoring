<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Bidang;
use App\Support\Filter\FilterBidang;
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
            Bidang::query()->with(['instansi', 'kendalaAktif'])->orderBy('nomor_urut')
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
            'Pengguna barang',
            'Penggunaan',
            'Desa',
            'Kecamatan',
            'Luas (m2)',
            'Jenis hak',
            'Alas hak',
            'Tanggal alas hak',
            'Asal usul',
            'Nomor berkas KKP',
            'Nomor sertipikat',
            'Tahun target',
            'Tanggal diproses',
        ];

        foreach (Tahapan::semua() as $tahap) {
            $judul[] = $tahap->urutan.'. '.$tahap->label;
        }

        return array_merge($judul, [
            'Tahap aktif',
            'Sedang menunggu',
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
            $row->pengguna_barang,
            $row->penggunaan,
            $row->desa,
            $row->kecamatan,
            $row->luas_m2 !== null ? (float) $row->luas_m2 : null,
            $row->jenis_hak,
            $row->alas_hak,
            $row->tgl_alas_hak !== null ? Tanggal::pendek($row->tgl_alas_hak) : '',
            $row->asal_usul,
            $row->nomor_berkas_kkp,
            $row->nomor_sertipikat,
            $row->tahun_target,
            $row->tgl_diproses !== null ? Tanggal::pendek($row->tgl_diproses) : '',
        ];

        foreach (Tahapan::semua() as $tahap) {
            $tanggal = $row->tanggalTahap($tahap);
            $baris[] = $tanggal !== null ? Tanggal::pendek($tanggal) : '';
        }

        $aktif = $row->tahapAktif;

        return array_merge($baris, [
            $aktif !== null ? $aktif->label : 'Belum mulai',
            $row->kondisiTahap,
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
}
