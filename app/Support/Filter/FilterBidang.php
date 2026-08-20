<?php

declare(strict_types=1);

namespace App\Support\Filter;

use App\Enums\KategoriKendala;
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

    /**
     * Nilai penyaring pantauan: bidang yang sedang dikerjakan, atau yang masih
     * menunggu giliran di daftar aset.
     */
    public const DIPANTAU = 'dipantau';

    public const ANTREAN = 'antrean';

    public function __construct(
        public ?string $cari = null,
        public ?string $desa = null,
        public ?int $instansiId = null,
        public ?string $penggunaBarang = null,
        public ?string $pantauan = null,
        public ?string $tahapAktif = null,
        public ?PenanggungJawab $penanggungJawab = null,
        public ?StatusBidang $status = null,
        public ?KategoriKendala $kategoriKendala = null,
        public ?int $tahunTarget = null,
    ) {}

    public static function dariRequest(Request $request): self
    {
        $cari = trim((string) $request->query('cari', ''));
        $tahap = (string) $request->query('tahap', '');
        $pengguna = trim((string) $request->query('pengguna_barang', ''));

        return new self(
            cari: $cari === '' ? null : $cari,
            desa: self::desaSah((string) $request->query('desa', '')),
            instansiId: self::angka($request->query('instansi')),
            penggunaBarang: $pengguna === '' ? null : $pengguna,
            pantauan: self::pantauanSah((string) $request->query('pantauan', '')),
            tahapAktif: self::tahapSah($tahap),
            penanggungJawab: PenanggungJawab::tryFrom((string) $request->query('penanggung_jawab', '')),
            status: StatusBidang::tryFrom((string) $request->query('status', '')),
            kategoriKendala: KategoriKendala::tryFrom((string) $request->query('kendala', '')),
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

        // Diketik bebas, bukan dipilih dari daftar: nama desa pada daftar aset
        // ditulis beragam, dan operator sering hanya ingat penggalannya.
        if ($this->desa !== null) {
            $query->where('desa', 'like', '%'.$this->desa.'%');
        }

        if ($this->instansiId !== null) {
            $query->where('instansi_id', $this->instansiId);
        }

        // Seluruh aset satu pemda bernaung di bawah satu instansi pemegang
        // hak, jadi penyaring inilah yang memisahkan berkas per perangkat
        // daerah pemakainya.
        if ($this->penggunaBarang !== null) {
            $query->where('pengguna_barang', $this->penggunaBarang);
        }

        if ($this->pantauan === self::DIPANTAU) {
            $query->dipantau();
        } elseif ($this->pantauan === self::ANTREAN) {
            // Aset yang sertipikatnya sudah terbit bukan antrean, meski tidak
            // pernah masuk pantauan: sertipikatnya terbit jauh sebelum papan
            // pantau ini ada. Yang antre adalah yang memang belum digarap.
            $query->dipantau(false)->whereNull(Bidang::KOLOM_TERBIT);
        }

        if ($this->status !== null) {
            $query->where('status', $this->status);
        }

        if ($this->tahunTarget !== null) {
            $query->where('tahun_target', $this->tahunTarget);
        }

        // Kategori disaring atas kendala yang MASIH terbuka — kendala yang
        // sudah ditutup tersimpan sebagai riwayat, bukan kondisi berjalan.
        if ($this->kategoriKendala !== null) {
            $query->whereHas(
                'kendalaAktif',
                fn (Builder $bagian) => $bagian->where('kategori', $this->kategoriKendala)
            );
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
            'desa' => $this->desa,
            'instansi' => $this->instansiId !== null ? (string) $this->instansiId : null,
            'pengguna_barang' => $this->penggunaBarang,
            'pantauan' => $this->pantauan,
            'tahap' => $this->tahapAktif,
            'penanggung_jawab' => $this->penanggungJawab?->value,
            'status' => $this->status?->value,
            'kendala' => $this->kategoriKendala?->value,
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

    /**
     * Kata kunci desa, sudah dilepas sebutan "Desa" atau "Kelurahan" di
     * depannya.
     *
     * Kolom `desa` menyimpan namanya saja — begitulah daftar aset dibaca
     * App\Imports\DaftarAsetPemda. Tanpa pelepasan ini, mengetik "Desa
     * Ulantha" apa adanya dari berkas pemda justru tidak menemukan apa pun.
     */
    private static function desaSah(string $nilai): ?string
    {
        $bersih = trim(preg_replace('/\s+/u', ' ', $nilai) ?? $nilai);
        $bersih = trim(preg_replace('/^(desa|des|kelurahan|kel)\b\.?,?\s*/iu', '', $bersih) ?? $bersih);

        return $bersih === '' ? null : $bersih;
    }

    private static function pantauanSah(string $nilai): ?string
    {
        return in_array($nilai, [self::DIPANTAU, self::ANTREAN], true) ? $nilai : null;
    }

    /**
     * @return array<string, string>
     */
    public static function pilihanPantauan(): array
    {
        return [
            self::DIPANTAU => 'Sedang diproses',
            self::ANTREAN => 'Belum diproses',
        ];
    }

    private static function tahapSah(string $kolom): ?string
    {
        if ($kolom === self::BELUM_MULAI) {
            return $kolom;
        }

        return Tahapan::cari($kolom) !== null ? $kolom : null;
    }
}
