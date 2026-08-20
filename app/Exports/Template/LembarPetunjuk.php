<?php

declare(strict_types=1);

namespace App\Exports\Template;

use App\Imports\PembacaBidang;
use App\Models\Instansi;
use App\Support\Impor\KolomImpor;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Lembar penjelasan template impor.
 *
 * Isinya dua hal yang tidak muat di baris judul lembar isian: arti tiap kolom,
 * dan daftar nama instansi yang sah untuk disalin ke kolom instansi. Daftar
 * itu dibangkitkan dari database saat template diunduh, jadi selalu mutakhir.
 */
class LembarPetunjuk implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    /**
     * Nomor baris judul tabel, diisi array() dan dibaca styles().
     */
    private int $barisJudulKolom = 0;

    private int $barisJudulInstansi = 0;

    public function title(): string
    {
        return 'Petunjuk';
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        $baris = [
            ['Petunjuk pengisian berkas impor bidang'],
            [],
            ['Isi data pada lembar "Data Bidang". Jangan mengubah baris judulnya —'],
            ['kolom dikenali dari judul itu. Urutan kolom boleh diubah, kolom tambahan diabaikan.'],
            ['Satu baris berarti satu bidang. Maksimal '.PembacaBidang::MAKSIMAL_BARIS.' baris data sekali unggah.'],
            ['Kendala tidak diimpor; catat lewat halaman detail bidang setelah data masuk.'],
            [],
        ];

        $this->barisJudulKolom = count($baris) + 1;

        $baris[] = ['Kolom', 'Wajib diisi', 'Penjelasan'];

        foreach (KolomImpor::semua() as $kolom) {
            $baris[] = [$kolom->judul, $kolom->wajib ? 'Ya' : 'Tidak', $kolom->petunjuk];
        }

        $baris[] = [];

        $this->barisJudulInstansi = count($baris) + 1;

        $baris[] = ['Nama instansi yang boleh ditulis pada kolom "'.KolomImpor::JUDUL_INSTANSI.'"', 'Jenis'];

        $instansi = Instansi::query()->aktif()->with('jenis')->orderBy('nama')->get();

        if ($instansi->isEmpty()) {
            $baris[] = ['(belum ada instansi aktif — tambahkan dulu lewat menu Instansi)'];

            return $baris;
        }

        foreach ($instansi as $satu) {
            $baris[] = [$satu->nama, $satu->jenis->nama];
        }

        return $baris;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('C')->getAlignment()->setWrapText(true);
        $sheet->getColumnDimension('C')->setAutoSize(false)->setWidth(70);

        return [
            1 => ['font' => ['bold' => true, 'size' => 13]],
            $this->barisJudulKolom => ['font' => ['bold' => true]],
            $this->barisJudulInstansi => ['font' => ['bold' => true]],
        ];
    }
}
