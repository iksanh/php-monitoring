<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusBidang;
use App\Models\Bidang;
use App\Models\Instansi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Shared\Date as TanggalExcel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Berkas tiruan dibuat menyerupai keluaran aplikasi barang milik daerah: kop
 * dua baris, judul bertingkat dua, dan tiga lembar yang membagi tanah menurut
 * sudah atau belum bersertipikat.
 */
class ImporDaftarAsetTest extends TestCase
{
    use RefreshDatabase;

    private const LEMBAR_SUDAH = 'Tanah Yang Bersertifikat';

    private const LEMBAR_BELUM = 'Tanah Yg BLM Bersertifikat';

    public function test_memuat_kedua_lembar_dan_mengabaikan_rekap_gabungan(): void
    {
        $instansi = Instansi::factory()->create();

        $this->artisan('impor:daftar-aset', ['berkas' => $this->berkas()])
            ->assertSuccessful();

        // Tiga baris: satu dari lembar bersertipikat, dua dari lembar belum.
        // Lembar "Data Seluruh Tanah" berisi salinan keduanya dan dilewati.
        $this->assertDatabaseCount('bidang', 3);
        $this->assertSame(3, Bidang::query()->where('instansi_id', $instansi->id)->count());
    }

    public function test_bidang_bersertipikat_dicatat_sudah_diserahkan(): void
    {
        Instansi::factory()->create();

        $this->artisan('impor:daftar-aset', ['berkas' => $this->berkas()])->assertSuccessful();

        $bidang = Bidang::query()->where('nomor_urut', '1.3.1.01.001.004.002/000075')->firstOrFail();

        $this->assertSame('2023-03-29', $bidang->tgl_sertipikat?->format('Y-m-d'));
        $this->assertSame('HP. NO. 00007 Desa Mamungga', $bidang->nomor_sertipikat);
        $this->assertNull($bidang->alas_hak);
        $this->assertNull($bidang->tgl_alas_hak);

        // Sertipikatnya sudah tercatat di daftar aset pemda, artinya sudah
        // berada di tangan pemiliknya.
        $this->assertSame('2023-03-29', $bidang->tgl_serah_terima?->format('Y-m-d'));
        $this->assertSame(StatusBidang::Diserahkan, $bidang->status);

        // Tahun capaian diambil dari terbitnya sertipikat, bukan tahun berjalan.
        $this->assertSame(2023, $bidang->tahun_target);
    }

    public function test_bidang_belum_bersertipikat_mencatat_alas_hak_bukan_sertipikat(): void
    {
        Instansi::factory()->create();

        $this->artisan('impor:daftar-aset', ['berkas' => $this->berkas()])->assertSuccessful();

        $bidang = Bidang::query()->where('nomor_urut', '1.3.1.01.001.004.002/000080')->firstOrFail();

        $this->assertSame('SPPHAT NO. 023/SPPHT-TKBL/III/2016', $bidang->alas_hak);
        $this->assertSame('2016-03-23', $bidang->tgl_alas_hak?->format('Y-m-d'));
        $this->assertNull($bidang->tgl_sertipikat);
        $this->assertNull($bidang->nomor_sertipikat);
        $this->assertSame((int) now()->format('Y'), $bidang->tahun_target);

        // Daftar aset adalah antrean: Kantor Pertanahan yang memilih bidang
        // mana yang benar-benar dikerjakan.
        $this->assertNull($bidang->tgl_diproses);
        $this->assertSame(StatusBidang::BelumDiproses, $bidang->status);
    }

    public function test_identitas_aset_dipetakan_dari_kolom_daftar_aset(): void
    {
        Instansi::factory()->create();

        $this->artisan('impor:daftar-aset', ['berkas' => $this->berkas()])->assertSuccessful();

        $bidang = Bidang::query()->where('nomor_urut', '1.3.1.01.001.004.002/000080')->firstOrFail();

        $this->assertSame('Tanah relokasi bangunan 12 ruang', $bidang->nama_aset);
        $this->assertSame('Tanah Bangunan Pendidikan Dan Latihan', $bidang->penggunaan);
        $this->assertSame('BAGIAN TATA PEMERINTAHAN', $bidang->pengguna_barang);
        $this->assertSame('Hak Pakai', $bidang->jenis_hak);
        $this->assertSame('Pembelian', $bidang->asal_usul);
        $this->assertSame('1000.00', $bidang->luas_m2);

        // Kode barang dan register tidak jadi kolom sendiri, tetapi jejaknya
        // harus tetap terbaca untuk rekonsiliasi dengan daftar aset.
        $this->assertStringContainsString('kode barang 1.3.1.01.001.004.002', (string) $bidang->keterangan);
        $this->assertStringContainsString('reg 000080', (string) $bidang->keterangan);
        $this->assertStringContainsString('perolehan 2016', (string) $bidang->keterangan);
    }

    public function test_letak_dipecah_jadi_desa_dan_kecamatan_bila_disebut(): void
    {
        Instansi::factory()->create();

        $this->artisan('impor:daftar-aset', ['berkas' => $this->berkas()])->assertSuccessful();

        $berkecamatan = Bidang::query()->where('nomor_urut', '1.3.1.01.001.004.002/000088')->firstOrFail();
        $this->assertSame('Pinomantiga', $berkecamatan->desa);
        $this->assertSame('Bulawa', $berkecamatan->kecamatan);

        // Sebagian besar baris berhenti di nama desa; kecamatan dibiarkan
        // kosong alih-alih dikarang.
        $tanpaKecamatan = Bidang::query()->where('nomor_urut', '1.3.1.01.001.004.002/000080')->firstOrFail();
        $this->assertSame('Moutong', $tanpaKecamatan->desa);
        $this->assertNull($tanpaKecamatan->kecamatan);
    }

    public function test_kode_barang_kembar_tetap_menghasilkan_nomor_urut_berbeda(): void
    {
        Instansi::factory()->create();

        $baris = $this->barisBelum();
        $baris[] = $this->baris(
            pengguna: 'BAGIAN TATA PEMERINTAHAN',
            jenisBarang: 'Tanah Bangunan Pendidikan Dan Latihan',
            kode: '1.3.1.01.001.004.002',
            register: '000080',
            luas: 900,
            tahun: 2016,
            letak: 'Desa Moutong',
            nomorDokumen: 'SPPHAT NO. 024/SPPHT-TKBL/III/2016',
            tanggalDokumen: '2016-03-24',
            penggunaan: 'Tanah relokasi bangunan 6 ruang',
        );

        $this->artisan('impor:daftar-aset', ['berkas' => $this->berkas(belum: $baris)])->assertSuccessful();

        $this->assertDatabaseHas('bidang', ['nomor_urut' => '1.3.1.01.001.004.002/000080']);
        $this->assertDatabaseHas('bidang', ['nomor_urut' => '1.3.1.01.001.004.002/000080-2']);
    }

    public function test_pratinjau_tidak_menulis_apa_pun(): void
    {
        Instansi::factory()->create();

        $this->artisan('impor:daftar-aset', ['berkas' => $this->berkas(), '--pratinjau' => true])
            ->assertSuccessful();

        $this->assertDatabaseCount('bidang', 0);
    }

    public function test_impor_ulang_tidak_menggandakan_bidang(): void
    {
        Instansi::factory()->create();
        $berkas = $this->berkas();

        $this->artisan('impor:daftar-aset', ['berkas' => $berkas])->assertSuccessful();
        $this->artisan('impor:daftar-aset', ['berkas' => $berkas])->assertSuccessful();

        $this->assertDatabaseCount('bidang', 3);
    }

    public function test_perbarui_menimpa_identitas_tanpa_mengosongkan_tanggal_sertipikat(): void
    {
        Instansi::factory()->create();
        $berkas = $this->berkas();

        $this->artisan('impor:daftar-aset', ['berkas' => $berkas])->assertSuccessful();

        $bidang = Bidang::query()->where('nomor_urut', '1.3.1.01.001.004.002/000080')->firstOrFail();
        $bidang->update(['nama_aset' => 'Diubah operator', 'tgl_sertipikat' => '2026-01-05']);

        $this->artisan('impor:daftar-aset', ['berkas' => $berkas, '--perbarui' => true])->assertSuccessful();

        $bidang->refresh();
        $this->assertSame('Tanah relokasi bangunan 12 ruang', $bidang->nama_aset);
        $this->assertSame('2026-01-05', $bidang->tgl_sertipikat?->format('Y-m-d'));

        // Mengisi tanggal tahap berarti pekerjaannya sudah jalan; impor ulang
        // tidak boleh menyeretnya kembali ke antrean.
        $this->assertNotNull($bidang->tgl_diproses);
    }

    public function test_instansi_wajib_ditegaskan_bila_pemda_lebih_dari_satu(): void
    {
        Instansi::factory()->count(2)->create();

        $this->artisan('impor:daftar-aset', ['berkas' => $this->berkas()])->assertFailed();

        $this->assertDatabaseCount('bidang', 0);
    }

    public function test_susunan_kolom_yang_berubah_ditolak(): void
    {
        Instansi::factory()->create();

        $judulKacau = $this->judulAtas();
        $judulKacau[9] = 'Nilai';

        $this->artisan('impor:daftar-aset', ['berkas' => $this->berkas(judulAtas: $judulKacau)])
            ->assertFailed();

        $this->assertDatabaseCount('bidang', 0);
    }

    /**
     * Berkas tiruan berisi tiga lembar, sama seperti berkas aslinya.
     *
     * @param  list<list<mixed>>|null  $belum
     * @param  list<mixed>|null  $judulAtas
     */
    private function berkas(?array $belum = null, ?array $judulAtas = null): string
    {
        $sudah = $this->barisSudah();
        $belum ??= $this->barisBelum();

        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        $lembar = [
            'Data Seluruh Tanah' => array_merge($sudah, $belum),
            self::LEMBAR_SUDAH => $sudah,
            self::LEMBAR_BELUM => $belum,
        ];

        foreach ($lembar as $nama => $baris) {
            $isi = array_merge([
                ['PEMERINTAH KABUPATEN UJI'],
                ['JUMLAH TOTAL BIDANG BMD TANAH'],
                [],
                $judulAtas ?? $this->judulAtas(),
                $this->judulBawah(),
            ], $baris);

            $spreadsheet->createSheet()->setTitle($nama)->fromArray($isi, null, 'A1', true);
        }

        $sementara = (string) tempnam(sys_get_temp_dir(), 'aset');
        $path = $sementara.'.xlsx';

        @unlink($sementara);
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    /**
     * @return list<mixed>
     */
    private function judulAtas(): array
    {
        return [
            'No.', 'Unit Organisasi', 'Sub Unit Organisasi', null,
            "Jenis Barang /\nNama Barang", null, "Jenis Barang /\nNama Barang",
            'N o m o r', null, "Luas\n(M2)", 'Tahun Penga-daan', "Letak/\nAlamat",
            'Hak', 'Sertifikat', null, 'Penggunaan', "Asal\nusul",
            "Harga\n(ribuan Rp)", "Harga\n(ribuan Rp)", 'Keterangan',
        ];
    }

    /**
     * @return list<mixed>
     */
    private function judulBawah(): array
    {
        $bawah = array_fill(0, 20, null);
        $bawah[7] = 'Kode Barang';
        $bawah[8] = "Regis-\nter";
        $bawah[13] = 'Tanggal';
        $bawah[14] = 'Nomor';

        return $bawah;
    }

    /**
     * @return list<list<mixed>>
     */
    private function barisSudah(): array
    {
        return [
            $this->baris(
                pengguna: 'BAGIAN TATA PEMERINTAHAN',
                jenisBarang: 'Tanah Bangunan Pendidikan Dan Latihan',
                kode: '1.3.1.01.001.004.002',
                register: '000075',
                luas: 10000,
                tahun: 2015,
                letak: 'Desa Mamungaa',
                nomorDokumen: 'HP. NO. 00007 Desa Mamungga',
                tanggalDokumen: '2023-03-29',
                penggunaan: 'Tanah SMPN 2 Bulawa',
            ),
        ];
    }

    /**
     * @return list<list<mixed>>
     */
    private function barisBelum(): array
    {
        return [
            $this->baris(
                pengguna: 'BAGIAN TATA PEMERINTAHAN',
                jenisBarang: 'Tanah Bangunan Pendidikan Dan Latihan',
                kode: '1.3.1.01.001.004.002',
                register: '000080',
                luas: 1000,
                tahun: 2016,
                letak: 'Desa Moutong',
                nomorDokumen: 'SPPHAT NO. 023/SPPHT-TKBL/III/2016',
                tanggalDokumen: '2016-03-23',
                penggunaan: 'Tanah relokasi bangunan 12 ruang',
            ),
            $this->baris(
                pengguna: 'BAGIAN TATA PEMERINTAHAN',
                jenisBarang: 'Tanah Bangunan Pendidikan Dan Latihan',
                kode: '1.3.1.01.001.004.002',
                register: '000088',
                luas: 2000,
                tahun: 2016,
                letak: 'DESA PINOMANTIGA KEC. BULAWA',
                nomorDokumen: 'SPPHAT NO. 16/BLW-BB/V/2016',
                tanggalDokumen: '2016-05-19',
                penggunaan: 'Tanah SMPN 3 Bulawa',
            ),
        ];
    }

    /**
     * Satu baris data pada posisi kolom daftar aset yang sesungguhnya.
     *
     * @return list<mixed>
     */
    private function baris(
        string $pengguna,
        string $jenisBarang,
        string $kode,
        string $register,
        float|int $luas,
        int $tahun,
        string $letak,
        string $nomorDokumen,
        string $tanggalDokumen,
        string $penggunaan,
        string $hak = 'Hak Pakai',
        string $asalUsul = 'Pembelian',
    ): array {
        $baris = array_fill(0, 20, null);

        $baris[0] = 1;
        $baris[1] = 'Sekretariat Daerah';
        $baris[2] = $pengguna;
        $baris[6] = $jenisBarang;
        $baris[7] = $kode;
        $baris[8] = $register;
        $baris[9] = $luas;
        $baris[10] = $tahun;
        $baris[11] = $letak;
        $baris[12] = $hak;
        // Sel tanggal ditulis sebagai angka seri, persis seperti berkas asli
        // yang dibaca tanpa format.
        $baris[13] = TanggalExcel::PHPToExcel(new \DateTimeImmutable($tanggalDokumen));
        $baris[14] = $nomorDokumen;
        $baris[15] = $penggunaan;
        $baris[16] = $asalUsul;
        $baris[17] = 100000;
        $baris[18] = 100000000;

        return $baris;
    }
}
