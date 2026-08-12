<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\KategoriKendala;
use App\Enums\PenanggungJawab;
use App\Enums\StatusBidang;
use App\Models\Bidang;
use App\Models\Kendala;
use App\Support\Dashboard\CapaianInstansi;
use App\Support\Dashboard\KartuAngka;
use App\Support\Dashboard\TahapTertahan;
use App\Support\Sql\SyaratTahap;
use App\Support\Tahap;
use App\Support\Tahapan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Seluruh query dashboard bermuara di sini — controller hanya merangkai.
 */
class DashboardService
{
    /**
     * Batas hari sebelum data dianggap basi dan diberi penanda di dashboard.
     */
    public const BATAS_BASI_HARI = 14;

    /**
     * Syarat tahap turunan dipakai bersama dengan penyaringan daftar bidang,
     * jadi SQL-nya hanya ditulis di satu tempat.
     */
    public function __construct(
        private readonly SyaratTahap $syarat = new SyaratTahap,
    ) {}

    public function kartuAngka(int $tahun): KartuAngka
    {
        $baris = Bidang::query()
            ->where('tahun_target', $tahun)
            ->select($this->syarat->ekspresi(
                'count(*) as jml_total,'
                .' sum(case when '.$this->syarat->kolomAman(Bidang::KOLOM_TERBIT).' is not null then 1 else 0 end) as jml_terbit,'
                .' sum(case when status = '.$this->syarat->nilaiAman(StatusBidang::Proses->value).' then 1 else 0 end) as jml_proses,'
                .' sum(case when status = '.$this->syarat->nilaiAman(StatusBidang::Diserahkan->value).' then 1 else 0 end) as jml_diserahkan,'
                .' sum(case when status = '.$this->syarat->nilaiAman(StatusBidang::Terkendala->value).' then 1 else 0 end) as jml_terkendala'
            ))
            ->first();

        return new KartuAngka(
            tahun: $tahun,
            total: $this->angka($baris, 'jml_total'),
            bersertipikat: $this->angka($baris, 'jml_terbit'),
            proses: $this->angka($baris, 'jml_proses'),
            terkendala: $this->angka($baris, 'jml_terkendala'),
            diserahkan: $this->angka($baris, 'jml_diserahkan'),
        );
    }

    /**
     * Sebaran bidang tertahan di tiap tahap — SATU query agregasi dengan
     * delapan `SUM(CASE WHEN ...)`, bukan delapan query terpisah.
     *
     * @return list<TahapTertahan>
     */
    public function sebaranTertahan(?int $tahun = null): array
    {
        $tahapan = Tahapan::semua();

        $pilih = [];
        foreach ($tahapan as $tahap) {
            $pilih[] = 'sum(case when '.$this->syarat->menunggu($tahap).' then 1 else 0 end) as '.$this->alias($tahap);
        }

        $baris = Bidang::query()
            ->when($tahun !== null, fn ($query) => $query->where('tahun_target', $tahun))
            ->select($this->syarat->ekspresi(implode(', ', $pilih)))
            ->first();

        return array_map(
            fn (Tahap $tahap): TahapTertahan => new TahapTertahan(
                $tahap,
                $this->angka($baris, $this->alias($tahap)),
            ),
            $tahapan,
        );
    }

    /**
     * Bidang tertahan menurut pihak yang memegang bola.
     *
     * Dihitung dari sebaran yang sama, jadi tidak menambah query. Pemisahan ini
     * yang mencegah Kantah selalu terlihat sebagai penyebab keterlambatan.
     *
     * @return array<string, int> nilai enum PenanggungJawab => jumlah
     */
    public function tertahanPerPenanggungJawab(?int $tahun = null): array
    {
        $hasil = [];

        foreach (PenanggungJawab::cases() as $pihak) {
            $hasil[$pihak->value] = 0;
        }

        foreach ($this->sebaranTertahan($tahun) as $tertahan) {
            $kunci = $tertahan->tahap->penanggungJawab->value;
            $hasil[$kunci] += $tertahan->jumlah;
        }

        return $hasil;
    }

    /**
     * Rincian bidang terkendala menurut kategori kendala aktifnya.
     *
     * Yang dihitung bidang, bukan baris kendala: satu bidang dengan dua kendala
     * kategori sama tetap terhitung sekali. Kategori tanpa isi tetap muncul
     * bernilai nol supaya rinciannya terbaca lengkap.
     *
     * @return array<string, int> nilai enum KategoriKendala => jumlah bidang
     */
    public function terkendalaPerKategori(?int $tahun = null): array
    {
        $hasil = [];

        foreach (KategoriKendala::cases() as $kategori) {
            $hasil[$kategori->value] = 0;
        }

        $baris = Kendala::query()
            ->join('bidang', 'bidang.id', '=', 'kendala.bidang_id')
            ->whereNull('kendala.tanggal_selesai')
            ->whereNull('bidang.deleted_at')
            ->when($tahun !== null, fn ($query) => $query->where('bidang.tahun_target', $tahun))
            ->groupBy('kendala.kategori')
            ->select('kendala.kategori')
            ->selectRaw('count(distinct kendala.bidang_id) as jml_bidang')
            ->get();

        foreach ($baris as $row) {
            $kategori = $row->getAttribute('kategori');
            $kunci = $kategori instanceof KategoriKendala ? $kategori->value : (string) $kategori;

            if (array_key_exists($kunci, $hasil)) {
                $hasil[$kunci] = $this->angka($row, 'jml_bidang');
            }
        }

        return $hasil;
    }

    /**
     * @return list<CapaianInstansi>
     */
    public function capaianPerInstansi(?int $tahun = null): array
    {
        $baris = Bidang::query()
            ->join('instansi', 'instansi.id', '=', 'bidang.instansi_id')
            ->when($tahun !== null, fn ($query) => $query->where('bidang.tahun_target', $tahun))
            ->groupBy('instansi.id', 'instansi.nama')
            ->orderBy('instansi.nama')
            ->select($this->syarat->ekspresi(
                'instansi.nama as nama_instansi,'
                .' count(*) as jml_total,'
                .' sum(case when bidang.'.$this->syarat->kolomAman(Bidang::KOLOM_TERBIT).' is not null then 1 else 0 end) as jml_terbit'
            ))
            ->get();

        return array_values($baris
            ->map(fn (Bidang $row): CapaianInstansi => new CapaianInstansi(
                nama: (string) $row->getAttribute('nama_instansi'),
                total: $this->angka($row, 'jml_total'),
                bersertipikat: $this->angka($row, 'jml_terbit'),
            ))
            ->all());
    }

    /**
     * Bidang paling lama berjalan yang sertipikatnya belum terbit.
     *
     * @return Collection<int, Bidang>
     */
    public function bidangTerlama(int $batas = 10): Collection
    {
        $kolomMulai = $this->syarat->kolomAman(Tahapan::pertama()->kolom);

        return Bidang::query()
            ->with(['instansi', 'kendalaAktif'])
            ->whereNull(Bidang::KOLOM_TERBIT)
            ->whereNotNull($kolomMulai)
            ->orderBy($kolomMulai)
            ->limit($batas)
            ->get();
    }

    public function pemutakhiranTerakhir(): ?CarbonImmutable
    {
        $nilai = Bidang::query()->max('updated_at');

        if (! is_string($nilai) && ! $nilai instanceof \DateTimeInterface) {
            return null;
        }

        return CarbonImmutable::parse($nilai);
    }

    public function dataBasi(?CarbonImmutable $pemutakhiran): bool
    {
        if ($pemutakhiran === null) {
            return true;
        }

        return $pemutakhiran->diffInDays(CarbonImmutable::now()) > self::BATAS_BASI_HARI;
    }

    /**
     * Diberi awalan supaya tidak bentrok dengan cast tanggal pada model.
     */
    private function alias(Tahap $tahap): string
    {
        return 'tertahan_'.$this->syarat->kolomAman($tahap->kolom);
    }

    private function angka(?Model $baris, string $kunci): int
    {
        $nilai = $baris?->getAttribute($kunci);

        return is_numeric($nilai) ? (int) $nilai : 0;
    }
}
