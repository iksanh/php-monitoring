<?php

declare(strict_types=1);

namespace App\Support\Filter;

use App\Enums\PenanggungJawab;
use App\Enums\StatusBidang;
use App\Models\Bidang;
use App\Support\Sql\SyaratTahap;
use App\Support\Tahap;
use App\Support\Tahapan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Penyaring daftar bidang, dipakai bersama oleh halaman daftar dan export.
 *
 * Dua penyaring bekerja atas nilai turunan yang tidak punya kolom sendiri —
 * tahap aktif dan penanggung jawab. Keduanya tetap dikerjakan di SQL lewat
 * SyaratTahap supaya pagination tetap server-side.
 */
final readonly class FilterBidang
{
    /**
     * Nilai penanda untuk bidang yang belum punya tanggal sama sekali.
     */
    public const BELUM_MULAI = 'belum';

    public function __construct(
        public ?string $cari = null,
        public ?int $instansiId = null,
        public ?string $tahapAktif = null,
        public ?PenanggungJawab $penanggungJawab = null,
        public ?StatusBidang $status = null,
        public ?int $tahunTarget = null,
    ) {}

    public static function dariRequest(Request $request): self
    {
        $cari = trim((string) $request->query('cari', ''));
        $tahap = (string) $request->query('tahap', '');

        return new self(
            cari: $cari === '' ? null : $cari,
            instansiId: self::angka($request->query('instansi')),
            tahapAktif: self::tahapSah($tahap),
            penanggungJawab: PenanggungJawab::tryFrom((string) $request->query('penanggung_jawab', '')),
            status: StatusBidang::tryFrom((string) $request->query('status', '')),
            tahunTarget: self::angka($request->query('tahun')),
        );
    }

    /**
     * @param  Builder<Bidang>  $query
     * @return Builder<Bidang>
     */
    public function terapkan(Builder $query): Builder
    {
        $syarat = new SyaratTahap;

        if ($this->cari !== null) {
            $kata = '%'.$this->cari.'%';

            $query->where(function (Builder $bagian) use ($kata): void {
                $bagian->where('nama_aset', 'like', $kata)
                    ->orWhere('nomor_urut', 'like', $kata);
            });
        }

        if ($this->instansiId !== null) {
            $query->where('instansi_id', $this->instansiId);
        }

        if ($this->status !== null) {
            $query->where('status', $this->status);
        }

        if ($this->tahunTarget !== null) {
            $query->where('tahun_target', $this->tahunTarget);
        }

        if ($this->tahapAktif === self::BELUM_MULAI) {
            $query->where($syarat->ekspresi($syarat->belumMulai()), '=', 1);
        } elseif ($this->tahapAktif !== null) {
            $tahap = Tahapan::cari($this->tahapAktif);

            if ($tahap !== null) {
                $query->where($syarat->ekspresi($syarat->aktif($tahap)), '=', 1);
            }
        }

        if ($this->penanggungJawab !== null) {
            $query->where(
                $syarat->ekspresi($syarat->menungguSalahSatu($this->tahapMilik($this->penanggungJawab))),
                '=',
                1
            );
        }

        return $query;
    }

    public function adaFilter(): bool
    {
        return $this->queryString() !== [];
    }

    /**
     * Nilai filter untuk dipasang kembali ke query string dan form.
     *
     * @return array<string, string>
     */
    public function queryString(): array
    {
        return array_filter([
            'cari' => $this->cari,
            'instansi' => $this->instansiId !== null ? (string) $this->instansiId : null,
            'tahap' => $this->tahapAktif,
            'penanggung_jawab' => $this->penanggungJawab?->value,
            'status' => $this->status?->value,
            'tahun' => $this->tahunTarget !== null ? (string) $this->tahunTarget : null,
        ], fn (?string $nilai): bool => $nilai !== null && $nilai !== '');
    }

    /**
     * Seluruh tahap yang penanggung jawabnya pihak tersebut.
     *
     * @return list<Tahap>
     */
    private function tahapMilik(PenanggungJawab $pihak): array
    {
        return array_values(array_filter(
            Tahapan::semua(),
            fn (Tahap $tahap): bool => $tahap->penanggungJawab === $pihak,
        ));
    }

    private static function angka(mixed $nilai): ?int
    {
        return is_numeric($nilai) ? (int) $nilai : null;
    }

    private static function tahapSah(string $kolom): ?string
    {
        if ($kolom === self::BELUM_MULAI) {
            return $kolom;
        }

        return Tahapan::cari($kolom) !== null ? $kolom : null;
    }
}
