<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\Bidang;
use App\Models\Instansi;
use App\Support\Impor\BarisImpor;
use App\Support\Impor\HasilImpor;
use App\Support\Impor\KolomImpor;
use App\Support\Tahapan;
use App\Support\Tanggal;
use DateTimeInterface;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as TanggalExcel;
use Throwable;

/**
 * Pembaca berkas impor bidang.
 *
 * Membaca saja — tidak menyentuh database untuk menulis. Hasilnya dipakai dua
 * kali: sekali untuk menampilkan pratinjau, sekali lagi saat operator menekan
 * konfirmasi. Karena berkasnya dibaca ulang, tidak ada data hasil validasi
 * yang perlu dititipkan lewat session.
 *
 * Kolom dicocokkan lewat judulnya, bukan posisinya, sehingga urutan kolom
 * boleh berbeda dan kolom asing (misalnya kolom turunan milik berkas hasil
 * export) diabaikan begitu saja.
 */
final class PembacaBidang
{
    /**
     * Batas jumlah baris data. Impor berjalan sinkron di shared hosting tanpa
     * queue worker, jadi berkas raksasa akan menabrak batas waktu PHP dan
     * gagal di tengah jalan. Lebih baik ditolak sejak awal dengan pesan jelas.
     */
    public const MAKSIMAL_BARIS = 1000;

    /**
     * @param  bool  $bolehPerbarui  Nomor urut yang sudah ada diperbarui,
     *                               bukan ditolak sebagai kembar.
     */
    public function __construct(private readonly bool $bolehPerbarui = false) {}

    public function baca(string $path): HasilImpor
    {
        try {
            $lembar = Excel::toArray(new class implements WithCalculatedFormulas {}, $path);
        } catch (Throwable $e) {
            return HasilImpor::gagal('Berkas tidak dapat dibaca. Pastikan formatnya .xlsx, .xls, atau .csv. ('.$e->getMessage().')');
        }

        /** @var list<list<mixed>> $baris */
        $baris = $lembar[0] ?? [];

        if ($baris === []) {
            return HasilImpor::gagal('Berkas kosong — tidak ada satu baris pun di dalamnya.');
        }

        $petaKolom = $this->petaKolom(array_shift($baris));

        if (is_string($petaKolom)) {
            return HasilImpor::gagal($petaKolom);
        }

        $isi = $this->buangBarisKosong($baris);

        if ($isi === []) {
            return HasilImpor::gagal('Berkas hanya berisi baris judul, belum ada data bidang.');
        }

        if (count($isi) > self::MAKSIMAL_BARIS) {
            return HasilImpor::gagal(
                'Berkas berisi '.count($isi).' baris data, melebihi batas '.self::MAKSIMAL_BARIS.' baris sekali unggah. Pecah menjadi beberapa berkas.'
            );
        }

        return new HasilImpor($this->bacaBaris($isi, $petaKolom));
    }

    /**
     * Peta kunci kolom ke nomor kolom pada berkas. Mengembalikan pesan galat
     * bila ada kolom wajib yang tidak ditemukan.
     *
     * @param  list<mixed>  $header
     * @return array<string, int>|string
     */
    private function petaKolom(array $header): array|string
    {
        $tersedia = [];

        foreach ($header as $nomor => $judul) {
            if (is_scalar($judul)) {
                $tersedia[KolomImpor::normalkan((string) $judul)] = (int) $nomor;
            }
        }

        $peta = [];
        $hilang = [];

        foreach (KolomImpor::semua() as $kolom) {
            $kunci = KolomImpor::normalkan($kolom->judul);

            if (isset($tersedia[$kunci])) {
                $peta[$kolom->kunci] = $tersedia[$kunci];

                continue;
            }

            if ($kolom->wajib) {
                $hilang[] = $kolom->judul;
            }
        }

        if ($hilang !== []) {
            return 'Kolom wajib tidak ditemukan pada baris judul: '.implode(', ', $hilang)
                .'. Gunakan berkas template supaya judul kolomnya tepat.';
        }

        return $peta;
    }

    /**
     * Baris yang seluruh selnya kosong dibuang, tetapi nomor baris aslinya
     * dipertahankan supaya pesan galat menunjuk baris yang benar di Excel.
     *
     * @param  list<list<mixed>>  $baris
     * @return array<int, list<mixed>>
     */
    private function buangBarisKosong(array $baris): array
    {
        $isi = [];

        foreach ($baris as $nomor => $sel) {
            $adaIsi = false;

            foreach ($sel as $nilai) {
                if ($nilai !== null && trim((string) (is_scalar($nilai) ? $nilai : 'x')) !== '') {
                    $adaIsi = true;

                    break;
                }
            }

            if ($adaIsi) {
                // +2: satu untuk baris judul yang sudah diambil, satu lagi
                // karena penomoran Excel mulai dari 1.
                $isi[$nomor + 2] = $sel;
            }
        }

        return $isi;
    }

    /**
     * @param  array<int, list<mixed>>  $isi
     * @param  array<string, int>  $petaKolom
     * @return list<BarisImpor>
     */
    private function bacaBaris(array $isi, array $petaKolom): array
    {
        $instansi = $this->daftarInstansi();
        $bidangAda = $this->daftarNomorUrut();

        $hasil = [];
        $nomorTerpakai = [];

        foreach ($isi as $nomorBaris => $sel) {
            $mentah = $this->mentah($sel, $petaKolom);
            $galat = [];

            $namaInstansi = trim((string) ($mentah['instansi'] ?? ''));
            $instansiId = $instansi[KolomImpor::normalkan($namaInstansi)] ?? null;

            if ($namaInstansi !== '' && $instansiId === null) {
                $galat[] = 'Instansi "'.$namaInstansi.'" tidak terdaftar. Salin persis salah satu nama dari sheet Petunjuk.';
            }

            $nilai = $this->normalkan($mentah, $galat);
            $nilai['instansi_id'] = $instansiId;

            $nomorUrut = (string) ($nilai['nomor_urut'] ?? '');
            $kunciNomor = mb_strtolower($nomorUrut);

            $galat = array_merge($galat, $this->galatValidasi($nilai));

            $lama = $bidangAda[$kunciNomor] ?? null;
            $bidangId = null;

            if ($nomorUrut !== '' && isset($nomorTerpakai[$kunciNomor])) {
                $galat[] = 'Nomor urut "'.$nomorUrut.'" muncul dua kali dalam berkas ini (baris '.$nomorTerpakai[$kunciNomor].').';
            } elseif ($lama !== null && $lama['diarsipkan']) {
                $galat[] = 'Nomor urut "'.$nomorUrut.'" masih dipakai bidang yang ada di arsip. Pulihkan dulu bidang itu atau pakai nomor lain.';
            } elseif ($lama !== null && ! $this->bolehPerbarui) {
                $galat[] = 'Nomor urut "'.$nomorUrut.'" sudah terdaftar. Centang "perbarui bidang yang sudah ada" bila memang ingin menimpanya.';
            } elseif ($lama !== null) {
                $bidangId = $lama['id'];
            }

            if ($nomorUrut !== '') {
                $nomorTerpakai[$kunciNomor] = $nomorBaris;
            }

            $hasil[] = new BarisImpor(
                nomorBaris: $nomorBaris,
                nilai: $nilai,
                galat: $galat,
                bidangId: $bidangId,
                nomorUrut: $nomorUrut,
                namaAset: (string) ($nilai['nama_aset'] ?? ''),
                instansi: $namaInstansi,
            );
        }

        return $hasil;
    }

    /**
     * Nilai mentah tiap kolom, masih apa adanya dari berkas.
     *
     * @param  list<mixed>  $sel
     * @param  array<string, int>  $petaKolom
     * @return array<string, mixed>
     */
    private function mentah(array $sel, array $petaKolom): array
    {
        $mentah = [];

        foreach ($petaKolom as $kunci => $nomor) {
            $mentah[$kunci] = $sel[$nomor] ?? null;
        }

        return $mentah;
    }

    /**
     * @param  array<string, mixed>  $mentah
     * @param  list<string>  $galat
     * @return array<string, mixed>
     */
    private function normalkan(array $mentah, array &$galat): array
    {
        $nilai = [];

        foreach (KolomImpor::semua() as $kolom) {
            if ($kolom->jenis === KolomImpor::INSTANSI) {
                continue;
            }

            $isi = $mentah[$kolom->kunci] ?? null;

            $nilai[$kolom->kunci] = match ($kolom->jenis) {
                KolomImpor::TANGGAL => $this->tanggal($isi, $kolom->judul, $galat),
                KolomImpor::ANGKA => $this->angka($isi, $kolom->judul, $galat),
                KolomImpor::TAHUN => $this->tahun($isi),
                default => $this->teks($isi),
            };
        }

        return $nilai;
    }

    private function teks(mixed $isi): ?string
    {
        if ($isi === null || is_array($isi) || is_object($isi)) {
            return null;
        }

        $teks = trim((string) $isi);

        return $teks === '' ? null : $teks;
    }

    private function tahun(mixed $isi): ?int
    {
        $teks = $this->teks($isi);

        return $teks === null ? null : (int) round((float) str_replace(',', '.', $teks));
    }

    /**
     * Luas boleh ditulis gaya Indonesia (1.250,75) maupun gaya mesin
     * (1250.75). Titik dianggap pemisah desimal hanya bila muncul sekali dan
     * diikuti paling banyak dua angka.
     *
     * @param  list<string>  $galat
     */
    private function angka(mixed $isi, string $judul, array &$galat): ?float
    {
        if (is_int($isi) || is_float($isi)) {
            return (float) $isi;
        }

        $teks = $this->teks($isi);

        if ($teks === null) {
            return null;
        }

        $bersih = str_replace([' ', "\u{a0}"], '', $teks);

        if (str_contains($bersih, ',')) {
            $bersih = str_replace(['.', ','], ['', '.'], $bersih);
        } elseif (substr_count($bersih, '.') > 1 || ! (bool) preg_match('/\.\d{1,2}$/', $bersih)) {
            $bersih = str_replace('.', '', $bersih);
        }

        if (! is_numeric($bersih)) {
            $galat[] = 'Kolom "'.$judul.'" berisi "'.$teks.'", yang bukan angka.';

            return null;
        }

        return (float) $bersih;
    }

    /**
     * Sel tanggal bisa datang sebagai objek tanggal, sebagai angka seri Excel,
     * atau sebagai teks ketikan operator. Ketiganya dipulangkan ke Y-m-d.
     *
     * @param  list<string>  $galat
     */
    private function tanggal(mixed $isi, string $judul, array &$galat): ?string
    {
        if ($isi instanceof DateTimeInterface) {
            return $isi->format('Y-m-d');
        }

        if (is_int($isi) || is_float($isi)) {
            if ($isi <= 0) {
                $galat[] = 'Kolom "'.$judul.'" berisi "'.$isi.'", yang bukan tanggal.';

                return null;
            }

            return TanggalExcel::excelToDateTimeObject($isi)->format('Y-m-d');
        }

        $teks = $this->teks($isi);

        if ($teks === null) {
            return null;
        }

        $tanggal = Tanggal::dariTeks($teks);

        if ($tanggal === null) {
            $galat[] = 'Kolom "'.$judul.'" berisi "'.$teks.'". Tulis tanggal sebagai 12/08/'.date('Y').' atau isi selnya dengan format tanggal Excel.';
        }

        return $tanggal;
    }

    /**
     * Validasi baku per baris. Aturannya sengaja sejalan dengan
     * App\Http\Requests\BidangRequest, ditambah batas tahun pada kolom tanggal
     * untuk menangkap sel yang ternyata berisi angka biasa.
     *
     * @param  array<string, mixed>  $nilai
     * @return list<string>
     */
    private function galatValidasi(array $nilai): array
    {
        $aturan = [
            'nomor_urut' => ['required', 'string', 'max:50'],
            'nama_aset' => ['required', 'string', 'max:255'],
            'instansi_id' => ['required', 'integer'],
            'pengguna_barang' => ['nullable', 'string', 'max:255'],
            'penggunaan' => ['nullable', 'string', 'max:255'],
            'desa' => ['required', 'string', 'max:255'],
            'kecamatan' => ['nullable', 'string', 'max:255'],
            'luas_m2' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'jenis_hak' => ['nullable', 'string', 'max:50'],
            'alas_hak' => ['nullable', 'string', 'max:255'],
            // Alas hak boleh jauh lebih tua dari tanggal tahap — banyak surat
            // hibah dan SPPHT terbit puluhan tahun sebelum permohonan.
            'tgl_alas_hak' => ['nullable', 'date', 'before:2101-01-01'],
            'asal_usul' => ['nullable', 'string', 'max:50'],
            'nomor_berkas_kkp' => ['nullable', 'string', 'max:255'],
            'nomor_sertipikat' => ['nullable', 'string', 'max:255'],
            'tahun_target' => ['required', 'integer', 'min:2000', 'max:2100'],
            'keterangan' => ['nullable', 'string', 'max:5000'],
        ];

        // Batas tahun hanya dipasang pada kolom tahap, untuk menangkap sel
        // yang ternyata berisi angka biasa.
        foreach (Tahapan::semua() as $tahap) {
            $aturan[$tahap->kolom] = ['nullable', 'date', 'after:1999-12-31', 'before:2101-01-01'];
        }

        $nama = [];

        foreach (KolomImpor::semua() as $kolom) {
            $nama[$kolom->kunci] = $kolom->judul;
        }

        // Pesan instansi sudah ditulis sendiri saat pencocokan nama; aturan di
        // sini hanya menjaring sel yang memang dibiarkan kosong.
        $nama['instansi_id'] = KolomImpor::JUDUL_INSTANSI;

        $validator = Validator::make($nilai, $aturan, [], $nama);

        return $validator->fails() ? array_values($validator->errors()->all()) : [];
    }

    /**
     * Nama instansi aktif dalam bentuk ternormalisasi, sehingga selisih huruf
     * besar-kecil atau spasi berlebih tidak membuat baris ditolak.
     *
     * @return array<string, int>
     */
    private function daftarInstansi(): array
    {
        $peta = [];

        foreach (Instansi::query()->aktif()->get(['id', 'nama']) as $instansi) {
            $peta[KolomImpor::normalkan($instansi->nama)] = $instansi->id;
        }

        return $peta;
    }

    /**
     * Nomor urut yang sudah dipakai, termasuk milik bidang yang diarsipkan —
     * indeks unik di database tidak peduli soft delete.
     *
     * @return array<string, array{id: int, diarsipkan: bool}>
     */
    private function daftarNomorUrut(): array
    {
        $peta = [];

        foreach (Bidang::withTrashed()->get(['id', 'nomor_urut', 'deleted_at']) as $bidang) {
            $peta[mb_strtolower($bidang->nomor_urut)] = [
                'id' => $bidang->id,
                'diarsipkan' => $bidang->deleted_at !== null,
            ];
        }

        return $peta;
    }
}
