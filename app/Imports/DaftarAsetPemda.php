<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\Bidang;
use App\Support\Impor\HasilAsetPemda;
use App\Support\Tanggal;
use DateTimeInterface;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as TanggalExcel;
use RuntimeException;
use Throwable;

/**
 * Pembaca berkas "Daftar Aset berupa Tanah" milik pemda, apa adanya.
 *
 * Berkas ini keluaran aplikasi barang milik daerah, bukan template aplikasi
 * ini: judulnya dua baris bergabung, ada baris kop di atasnya, dan datanya
 * terbelah menjadi lembar sudah/belum bersertipikat. Karena itu ia tidak lewat
 * App\Imports\PembacaBidang, melainkan dibaca sekali lewat perintah konsol
 * saat memuat data awal.
 *
 * Yang diambil hanya yang berguna bagi pertanahan. Kolom harga perolehan dan
 * nilai buku ditinggalkan: aplikasi ini memantau sertipikasi, bukan menilai
 * aset. Kode barang dan register tidak jadi kolom sendiri — keduanya dititip
 * di keterangan supaya baris ini tetap bisa dicocokkan kembali dengan daftar
 * aset saat rekonsiliasi.
 */
final class DaftarAsetPemda
{
    /**
     * Letak kolom pada layout daftar aset, dihitung dari nol.
     *
     * Dicocokkan lewat posisi, bukan judul: judul "Jenis Barang / Nama Barang"
     * muncul dua kali pada baris yang sama sehingga pencocokan nama justru
     * menunjuk kolom yang salah. Layoutnya dijaga PENANDA di bawah.
     */
    private const KOLOM = [
        'pengguna_barang' => 2,
        'jenis_barang' => 6,
        'kode_barang' => 7,
        'register' => 8,
        'luas' => 9,
        'tahun_perolehan' => 10,
        'letak' => 11,
        'hak' => 12,
        'tgl_dokumen' => 13,
        'nomor_dokumen' => 14,
        'penggunaan' => 15,
        'asal_usul' => 16,
        'keterangan' => 19,
    ];

    /**
     * Kata yang wajib muncul pada judul kolom tertentu. Bila layout berkas
     * berubah, impor berhenti dengan pesan jelas alih-alih menulis data yang
     * salah kolom.
     */
    private const PENANDA = [
        2 => 'sub unit',
        9 => 'luas',
        15 => 'penggunaan',
        19 => 'keterangan',
    ];

    /**
     * Tahun target paling tua yang diterima kolom `tahun_target`; sertipikat
     * yang terbit sebelum itu dicatat pada tahun ini.
     */
    private const TAHUN_TARGET_TERTUA = 2000;

    private const PANJANG_NOMOR_URUT = 50;

    /**
     * @var array<string, int>
     */
    private array $nomorTerpakai = [];

    public function baca(string $path): HasilAsetPemda
    {
        $bidang = [];
        $perLembar = [];
        $catatan = [];
        $dilewati = 0;

        foreach ($this->lembar($path) as $nama => $baris) {
            $bersertipikat = $this->bersertipikat($nama);

            if ($bersertipikat === null) {
                $catatan[] = 'Lembar "'.$nama.'" dilewati: namanya tidak menyebut sudah atau belum bersertipikat.';

                continue;
            }

            $awal = $this->awalData($nama, $baris);
            $terbaca = 0;

            foreach (array_slice($baris, $awal) as $urutan => $sel) {
                $atribut = $this->atribut($sel, $bersertipikat);

                if ($atribut === null) {
                    $dilewati++;

                    continue;
                }

                $atribut['nomor_urut'] = $this->nomorUrut($sel, $nama, $awal + $urutan + 1);

                $bidang[] = $atribut;
                $terbaca++;
            }

            $perLembar[$nama] = $terbaca;
        }

        if ($bidang === []) {
            throw new RuntimeException(
                'Tidak ada satu baris pun yang terbaca. Pastikan berkasnya daftar aset tanah pemda, '
                .'dengan lembar bersertipikat dan belum bersertipikat.'
            );
        }

        return new HasilAsetPemda($bidang, $perLembar, $dilewati, $catatan);
    }

    /**
     * Isi tiap lembar sebagai matriks, dengan nama lembar sebagai kunci.
     *
     * Dibaca lewat PhpSpreadsheet langsung, bukan lewat facade Excel, karena
     * facade itu memulangkan lembar tanpa namanya — padahal nama lembar
     * justru yang menentukan bidangnya sudah bersertipikat atau belum.
     *
     * @return array<string, list<list<mixed>>>
     */
    private function lembar(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('Berkas tidak ditemukan: '.$path);
        }

        try {
            $pembaca = IOFactory::createReaderForFile($path);
            $pembaca->setReadDataOnly(true);
            $berkas = $pembaca->load($path);
        } catch (Throwable $e) {
            throw new RuntimeException('Berkas tidak dapat dibaca: '.$e->getMessage(), previous: $e);
        }

        $lembar = [];

        foreach ($berkas->getAllSheets() as $isi) {
            /** @var list<list<mixed>> $matriks */
            $matriks = $isi->toArray(null, true, false, false);
            $lembar[$isi->getTitle()] = $matriks;
        }

        return $lembar;
    }

    /**
     * true bila lembar berisi tanah yang sudah bersertipikat, false bila
     * belum, null bila lembar itu bukan keduanya (mis. rekap gabungan).
     *
     * Urutan pemeriksaan penting: "Tanah Yg BLM Bersertifikat" mengandung
     * kata "bersertifikat" juga.
     */
    private function bersertipikat(string $nama): ?bool
    {
        $bersih = mb_strtolower($nama);

        if (preg_match('/\b(blm|belum)\b/u', $bersih) === 1) {
            return false;
        }

        return str_contains($bersih, 'bersertifikat') || str_contains($bersih, 'bersertipikat')
            ? true
            : null;
    }

    /**
     * Nomor baris pertama yang berisi data: dua baris di bawah baris judul
     * "No.", karena judulnya terbagi dua tingkat.
     *
     * @param  list<list<mixed>>  $baris
     */
    private function awalData(string $lembar, array $baris): int
    {
        foreach ($baris as $nomor => $sel) {
            if (mb_strtolower(trim((string) ($sel[0] ?? ''))) !== 'no.') {
                continue;
            }

            $this->periksaLayout($lembar, $sel, $baris[$nomor + 1] ?? []);

            return $nomor + 2;
        }

        throw new RuntimeException(
            'Baris judul tidak ditemukan pada lembar "'.$lembar.'". '
            .'Kolom pertama baris judul harus berisi "No.".'
        );
    }

    /**
     * @param  list<mixed>  $atas
     * @param  list<mixed>  $bawah
     */
    private function periksaLayout(string $lembar, array $atas, array $bawah): void
    {
        foreach (self::PENANDA as $kolom => $kata) {
            $judul = mb_strtolower(trim((string) ($atas[$kolom] ?? '').' '.(string) ($bawah[$kolom] ?? '')));

            if (! str_contains($judul, $kata)) {
                throw new RuntimeException(
                    'Susunan kolom lembar "'.$lembar.'" tidak seperti daftar aset tanah pemda: '
                    .'kolom ke-'.($kolom + 1).' semestinya berjudul "'.$kata.'", terbaca "'.trim($judul).'". '
                    .'Berkas ini harus dibaca apa adanya, jangan disunting susunan kolomnya.'
                );
            }
        }
    }

    /**
     * Atribut bidang dari satu baris daftar aset, atau null bila barisnya
     * bukan data (baris kosong, garis rekapitulasi, sisa kop).
     *
     * @param  list<mixed>  $sel
     * @return array<string, mixed>|null
     */
    private function atribut(array $sel, bool $bersertipikat): ?array
    {
        $pengguna = $this->teks($this->sel($sel, 'pengguna_barang'));
        $letak = $this->teks($this->sel($sel, 'letak'));

        if ($pengguna === null && $letak === null) {
            return null;
        }

        [$desa, $kecamatan] = $this->pisahLetak($letak);

        $jenisBarang = $this->teks($this->sel($sel, 'jenis_barang'));
        $penggunaan = $this->teks($this->sel($sel, 'penggunaan'));
        $tanggalDokumen = $this->tanggal($this->sel($sel, 'tgl_dokumen'));
        $nomorDokumen = $this->teks($this->sel($sel, 'nomor_dokumen'));

        $atribut = [
            'nama_aset' => Str::limit($penggunaan ?? $jenisBarang ?? 'Tanah '.$desa, 250),
            'pengguna_barang' => Str::limit($pengguna ?? '', 250) ?: null,
            'penggunaan' => $jenisBarang !== null ? Str::limit($jenisBarang, 250) : null,
            'desa' => $desa,
            'kecamatan' => $kecamatan,
            'luas_m2' => $this->angka($this->sel($sel, 'luas')),
            'jenis_hak' => Str::limit((string) $this->teks($this->sel($sel, 'hak')), 45) ?: null,
            'alas_hak' => null,
            'tgl_alas_hak' => null,
            'asal_usul' => Str::limit((string) $this->teks($this->sel($sel, 'asal_usul')), 45) ?: null,
            'nomor_sertipikat' => null,
            Bidang::KOLOM_TERBIT => null,
            Bidang::KOLOM_SERAH_TERIMA => null,
            // Daftar aset adalah antrean, bukan pekerjaan berjalan: Kantor
            // Pertanahan memilih sendiri bidang mana yang diproses. Null di
            // sini tegas, bukan kelalaian — BidangObserver menganggap bidang
            // baru tanpa penanda ini sedang dikerjakan.
            'tgl_diproses' => null,
            'keterangan' => $this->keterangan($sel),
        ];

        // Kolom "Sertifikat" pada daftar aset berpindah arti menurut lembarnya:
        // di lembar yang sudah bersertipikat ia benar-benar sertipikat, di
        // lembar yang belum ia dokumen dasar penguasaan — SPPHT, surat hibah,
        // SKST. Menyamakan keduanya berarti melaporkan tanah bersertipikat
        // padahal belum.
        if ($bersertipikat) {
            $atribut['nomor_sertipikat'] = $nomorDokumen;
            $atribut[Bidang::KOLOM_TERBIT] = $tanggalDokumen;
            // Sertipikatnya sudah tercatat di daftar aset pemda, artinya sudah
            // berada di tangan pemiliknya. Tanggal serah terima tersendiri
            // tidak ada di berkas, jadi disamakan dengan terbitnya sertipikat.
            $atribut[Bidang::KOLOM_SERAH_TERIMA] = $tanggalDokumen;
        } else {
            $atribut['alas_hak'] = $nomorDokumen;
            $atribut['tgl_alas_hak'] = $tanggalDokumen;
        }

        $atribut['tahun_target'] = $this->tahunTarget($bersertipikat ? $tanggalDokumen : null);

        return $atribut;
    }

    /**
     * Penanda bidang, dibangun dari kode barang dan nomor register supaya
     * baris yang sama pada berkas berikutnya mengenai bidang yang sama —
     * inilah yang membuat perintah impor aman diulang.
     *
     * @param  list<mixed>  $sel
     */
    private function nomorUrut(array $sel, string $lembar, int $nomorBaris): string
    {
        $kode = $this->teks($this->sel($sel, 'kode_barang'));
        $register = $this->teks($this->sel($sel, 'register'));

        $dasar = match (true) {
            $kode !== null && $register !== null => $kode.'/'.$register,
            $kode !== null => $kode,
            default => 'ASET-'.Str::slug($lembar).'-'.$nomorBaris,
        };

        $dasar = Str::limit($dasar, self::PANJANG_NOMOR_URUT - 4, '');
        $kunci = mb_strtolower($dasar);

        // Beberapa baris memakai kode barang dan register yang sama persis —
        // bidang berbeda yang dicatat berpasangan. Akhiran urut membedakannya
        // tanpa mengaburkan asalnya.
        $urutan = ($this->nomorTerpakai[$kunci] ?? 0) + 1;
        $this->nomorTerpakai[$kunci] = $urutan;

        return $urutan === 1 ? $dasar : $dasar.'-'.$urutan;
    }

    /**
     * Tahun capaian bagi bidang yang sudah bersertipikat, tahun berjalan bagi
     * yang belum. Sertipikat lama dibulatkan ke tahun tertua yang diterima
     * kolomnya.
     */
    private function tahunTarget(?string $tglSertipikat): int
    {
        if ($tglSertipikat === null) {
            return (int) now()->format('Y');
        }

        return max(self::TAHUN_TARGET_TERTUA, (int) substr($tglSertipikat, 0, 4));
    }

    /**
     * Keterangan asli ditambah jejak asal barisnya. Kode barang dan register
     * disimpan di sini karena keduanya milik tata usaha barang daerah, bukan
     * data pertanahan — tetapi tetap dibutuhkan saat mencocokkan kembali.
     *
     * @param  list<mixed>  $sel
     */
    private function keterangan(array $sel): string
    {
        $bagian = [];
        $asli = $this->teks($this->sel($sel, 'keterangan'));

        if ($asli !== null) {
            $bagian[] = $asli;
        }

        $jejak = ['Daftar aset tanah pemda'];
        $kode = $this->teks($this->sel($sel, 'kode_barang'));
        $register = $this->teks($this->sel($sel, 'register'));
        $tahun = $this->teks($this->sel($sel, 'tahun_perolehan'));

        if ($kode !== null) {
            $jejak[] = 'kode barang '.$kode.($register !== null ? ' reg '.$register : '');
        }

        if ($tahun !== null) {
            $jejak[] = 'perolehan '.$tahun;
        }

        $bagian[] = implode(' · ', $jejak);

        return Str::limit(implode("\n", $bagian), 4900);
    }

    /**
     * Daftar aset hanya punya satu kolom letak. Sebagian barisnya menyebut
     * kecamatan di ekor teks, sebagian besar berhenti di nama desa.
     *
     * @return array{string, string|null}
     */
    private function pisahLetak(?string $letak): array
    {
        if ($letak === null) {
            return ['-', null];
        }

        $bagian = preg_split('/\s+kec(?:amatan)?\b\.?,?\s*/iu', $letak, 2) ?: [$letak];

        $desa = $this->bersihkanDesa($bagian[0]);
        $kecamatan = isset($bagian[1]) ? $this->judul(trim($bagian[1])) : null;

        return [
            $desa !== '' ? $desa : $this->judul($letak),
            $kecamatan !== '' ? $kecamatan : null,
        ];
    }

    /**
     * Membuang kata "Desa" atau "Kelurahan" di depan nama, lalu menyeragamkan
     * huruf besar-kecilnya — tanpa itu "DESA ULANTHA" dan "Desa Ulantha"
     * terhitung dua tempat berbeda pada penyaring dan grafik.
     */
    private function bersihkanDesa(string $teks): string
    {
        $bersih = preg_replace('/^(desa|des|kelurahan|kel)\b\.?,?\s*/iu', '', trim($teks)) ?? $teks;

        return $this->judul(trim($bersih));
    }

    private function judul(string $teks): string
    {
        return $teks === '' ? '' : Str::title($teks);
    }

    /**
     * @param  list<mixed>  $sel
     */
    private function sel(array $sel, string $kolom): mixed
    {
        return $sel[self::KOLOM[$kolom]] ?? null;
    }

    private function teks(mixed $isi): ?string
    {
        if ($isi === null || is_array($isi) || is_object($isi)) {
            return $isi instanceof DateTimeInterface ? $isi->format('Y-m-d') : null;
        }

        $teks = trim(preg_replace('/\s+/u', ' ', (string) $isi) ?? (string) $isi);

        return $teks === '' ? null : $teks;
    }

    private function angka(mixed $isi): ?float
    {
        if (is_int($isi) || is_float($isi)) {
            return (float) $isi;
        }

        $teks = $this->teks($isi);

        if ($teks === null) {
            return null;
        }

        // Aturannya sama dengan App\Imports\PembacaBidang: titik dianggap
        // pemisah desimal hanya bila muncul sekali dan diikuti paling banyak
        // dua angka, sehingga "1.250" tetap seribu dua ratus lima puluh.
        $bersih = str_replace([' ', "\u{a0}"], '', $teks);

        if (str_contains($bersih, ',')) {
            $bersih = str_replace(['.', ','], ['', '.'], $bersih);
        } elseif (substr_count($bersih, '.') > 1 || ! (bool) preg_match('/\.\d{1,2}$/', $bersih)) {
            $bersih = str_replace('.', '', $bersih);
        }

        return is_numeric($bersih) ? (float) $bersih : null;
    }

    /**
     * Sel tanggal datang sebagai angka seri Excel karena berkas dibaca tanpa
     * format; sebagian kecil diketik sebagai teks.
     */
    private function tanggal(mixed $isi): ?string
    {
        if ($isi instanceof DateTimeInterface) {
            return $isi->format('Y-m-d');
        }

        if ((is_int($isi) || is_float($isi)) && $isi > 0) {
            return TanggalExcel::excelToDateTimeObject($isi)->format('Y-m-d');
        }

        $teks = $this->teks($isi);

        return $teks === null ? null : Tanggal::dariTeks($teks);
    }
}
