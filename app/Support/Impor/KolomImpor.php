<?php

declare(strict_types=1);

namespace App\Support\Impor;

use App\Support\Tahapan;

/**
 * Definisi satu kolom berkas impor bidang.
 *
 * Dipakai bersama oleh template unduhan, pembaca berkas, dan halaman
 * pratinjau — judul kolom hanya ditulis sekali di sini. Kolom tanggal
 * dibangun dari config('tahapan'), bukan ditulis ulang.
 *
 * Judul sengaja disamakan dengan header App\Exports\BidangExport supaya
 * berkas hasil export dapat disunting lalu diunggah kembali; kolom turunan
 * milik export (tahap aktif, umur, progres, status) diabaikan pembaca.
 */
final readonly class KolomImpor
{
    public const TEKS = 'teks';

    public const ANGKA = 'angka';

    public const TAHUN = 'tahun';

    public const TANGGAL = 'tanggal';

    public const INSTANSI = 'instansi';

    /**
     * Judul kolom instansi. Dipisah sebagai konstanta karena pembaca dan
     * lembar petunjuk sama-sama menyebutnya di luar konteks perulangan.
     */
    public const JUDUL_INSTANSI = 'Instansi pemilik';

    public function __construct(
        public string $kunci,
        public string $judul,
        public string $jenis,
        public bool $wajib,
        public string $petunjuk,
    ) {}

    /**
     * Seluruh kolom berkas impor, sesuai urutan template.
     *
     * @return list<self>
     */
    public static function semua(): array
    {
        $kolom = [
            new self(
                kunci: 'nomor_urut',
                judul: 'Nomor urut',
                jenis: self::TEKS,
                wajib: true,
                petunjuk: 'Penanda bidang, harus berbeda dari bidang lain. Maksimal 50 karakter.',
            ),
            new self(
                kunci: 'nama_aset',
                judul: 'Nama aset',
                jenis: self::TEKS,
                wajib: true,
                petunjuk: 'Nama aset sebagaimana tercatat pada instansi pemilik.',
            ),
            new self(
                kunci: 'instansi',
                judul: self::JUDUL_INSTANSI,
                jenis: self::INSTANSI,
                wajib: true,
                petunjuk: 'Salin persis salah satu nama pada daftar instansi di bawah.',
            ),
            new self(
                kunci: 'pengguna_barang',
                judul: 'Pengguna barang',
                jenis: self::TEKS,
                wajib: false,
                petunjuk: 'Perangkat daerah pemakai aset, misalnya Dinas Pendidikan. Pemegang haknya tetap instansi di kolom sebelumnya.',
            ),
            new self(
                kunci: 'penggunaan',
                judul: 'Penggunaan',
                jenis: self::TEKS,
                wajib: false,
                petunjuk: 'Peruntukan aset, misalnya kantor, sekolah, atau puskesmas.',
            ),
            new self(
                kunci: 'desa',
                judul: 'Desa',
                jenis: self::TEKS,
                wajib: true,
                petunjuk: 'Nama desa atau kelurahan letak bidang.',
            ),
            new self(
                kunci: 'kecamatan',
                judul: 'Kecamatan',
                jenis: self::TEKS,
                wajib: false,
                petunjuk: 'Nama kecamatan letak bidang. Boleh dikosongkan bila belum diketahui.',
            ),
            new self(
                kunci: 'luas_m2',
                judul: 'Luas (m2)',
                jenis: self::ANGKA,
                wajib: false,
                petunjuk: 'Angka meter persegi. Boleh berkoma, misalnya 1.250,75 atau 1250.75.',
            ),
            new self(
                kunci: 'jenis_hak',
                judul: 'Jenis hak',
                jenis: self::TEKS,
                wajib: false,
                petunjuk: 'Hak yang dimohon atau yang sudah melekat, misalnya Hak Pakai atau Hak Pengelolaan.',
            ),
            new self(
                kunci: 'alas_hak',
                judul: 'Alas hak',
                jenis: self::TEKS,
                wajib: false,
                petunjuk: 'Dasar penguasaan sebelum sertipikat terbit, misalnya SPPHT, surat hibah, atau SKST.',
            ),
            new self(
                kunci: 'tgl_alas_hak',
                judul: 'Tanggal alas hak',
                jenis: self::TANGGAL,
                wajib: false,
                petunjuk: 'Tanggal dokumen alas hak. Boleh lebih tua dari tanggal tahap.',
            ),
            new self(
                kunci: 'asal_usul',
                judul: 'Asal usul',
                jenis: self::TEKS,
                wajib: false,
                petunjuk: 'Cara aset diperoleh, misalnya Hibah atau Pembelian.',
            ),
            new self(
                kunci: 'nomor_berkas_kkp',
                judul: 'Nomor berkas KKP',
                jenis: self::TEKS,
                wajib: false,
                petunjuk: 'Nomor berkas pada aplikasi KKP, bila sudah ada.',
            ),
            new self(
                kunci: 'nomor_sertipikat',
                judul: 'Nomor sertipikat',
                jenis: self::TEKS,
                wajib: false,
                petunjuk: 'Nomor sertipikat yang sudah terbit, bila ada.',
            ),
            new self(
                kunci: 'tahun_target',
                judul: 'Tahun target',
                jenis: self::TAHUN,
                wajib: true,
                petunjuk: 'Empat angka antara 2000 dan 2100, misalnya '.date('Y').'.',
            ),
        ];

        foreach (Tahapan::semua() as $tahap) {
            $kolom[] = new self(
                kunci: $tahap->kolom,
                judul: $tahap->urutan.'. '.$tahap->label,
                jenis: self::TANGGAL,
                wajib: false,
                petunjuk: 'Tanggal '.mb_strtolower($tahap->label).' selesai. Kosongkan bila belum.',
            );
        }

        $kolom[] = new self(
            kunci: 'keterangan',
            judul: 'Keterangan',
            jenis: self::TEKS,
            wajib: false,
            petunjuk: 'Catatan bebas. Kendala dicatat terpisah lewat halaman detail bidang.',
        );

        return $kolom;
    }

    /**
     * Judul seluruh kolom, dipakai sebagai baris header template.
     *
     * @return list<string>
     */
    public static function judul(): array
    {
        return array_map(static fn (self $kolom): string => $kolom->judul, self::semua());
    }

    /**
     * Judul kolom yang wajib ada di berkas unggahan. Kolom opsional boleh
     * dihilangkan sama sekali — nilainya dianggap kosong.
     *
     * @return list<string>
     */
    public static function judulWajib(): array
    {
        return array_values(array_map(
            static fn (self $kolom): string => $kolom->judul,
            array_filter(self::semua(), static fn (self $kolom): bool => $kolom->wajib),
        ));
    }

    /**
     * Bentuk judul yang dipakai sebagai kunci pencocokan header: huruf kecil,
     * tanpa spasi ganda, tanpa tanda baca. Membuat "Luas (m2)", "luas (M2)",
     * dan "Luas  m2" dianggap kolom yang sama.
     */
    public static function normalkan(string $judul): string
    {
        $bersih = mb_strtolower(trim($judul));
        $bersih = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $bersih) ?? $bersih;

        return trim(preg_replace('/\s+/', ' ', $bersih) ?? $bersih);
    }
}
