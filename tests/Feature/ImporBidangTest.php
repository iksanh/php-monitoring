<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Peran;
use App\Enums\StatusBidang;
use App\Models\Bidang;
use App\Models\Instansi;
use App\Models\User;
use App\Support\Impor\KolomImpor;
use App\Support\Tahapan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\Shared\Date as TanggalExcel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ImporBidangTest extends TestCase
{
    use RefreshDatabase;

    private ?User $operator = null;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_viewer_tidak_boleh_membuka_impor(): void
    {
        $viewer = User::factory()->peran(Peran::Viewer)->create();

        $this->actingAs($viewer)->get(route('bidang.impor.form'))->assertForbidden();
        $this->actingAs($viewer)->get(route('bidang.impor.template'))->assertForbidden();
    }

    public function test_operator_mengunduh_template(): void
    {
        $response = $this->actingAs($this->operator())->get(route('bidang.impor.template'));

        $response->assertOk();
        $this->assertStringContainsString(
            'template-impor-bidang.xlsx',
            (string) $response->headers->get('content-disposition')
        );
        $this->assertNotEmpty($response->streamedContent());
    }

    public function test_berkas_sah_menampilkan_pratinjau_tanpa_menyimpan_apa_pun(): void
    {
        $instansi = Instansi::factory()->create();

        $response = $this->unggah([
            $this->baris($instansi, 'HP-001', 'Kantor Desa Uji'),
            $this->baris($instansi, 'HP-002', 'Puskesmas Uji'),
        ]);

        $response->assertOk();
        $response->assertSee('Hasil pemeriksaan berkas');
        $response->assertSee('Simpan 2 bidang');

        $this->assertDatabaseCount('bidang', 0);
    }

    public function test_konfirmasi_menyimpan_bidang_beserta_status_turunannya(): void
    {
        $instansi = Instansi::factory()->create();

        $tuntas = $this->baris($instansi, 'HP-010', 'Kantor Camat Uji');

        // Seluruh tanggal tahap terisi: status harus jatuh ke `diserahkan`,
        // dan itu hanya terjadi bila impor lewat event model.
        foreach (Tahapan::semua() as $tahap) {
            $tuntas[$this->kolomKe($tahap->kolom)] = '12/08/2026';
        }

        $this->unggah([$tuntas])->assertOk();

        $this->actingAs($this->operator())
            ->post(route('bidang.impor.simpan'))
            ->assertRedirect(route('bidang.index'))
            ->assertSessionHas('sukses');

        $this->assertDatabaseCount('bidang', 1);

        $bidang = Bidang::query()->firstOrFail();

        $this->assertSame('HP-010', $bidang->nomor_urut);
        $this->assertSame($instansi->id, $bidang->instansi_id);
        $this->assertSame(StatusBidang::Diserahkan, $bidang->status);
        $this->assertSame('2026-08-12', $bidang->{Tahapan::pertama()->kolom}->format('Y-m-d'));
    }

    public function test_baris_bergalat_menghalangi_seluruh_penyimpanan(): void
    {
        $instansi = Instansi::factory()->create();

        $rusak = $this->baris($instansi, 'HP-021', 'Aset Tanpa Instansi');
        $rusak[$this->kolomKe('instansi')] = 'Dinas Yang Tidak Terdaftar';

        $response = $this->unggah([
            $this->baris($instansi, 'HP-020', 'Aset Benar'),
            $rusak,
        ]);

        $response->assertOk();
        $response->assertSee('1 baris perlu dibetulkan dulu.');
        $response->assertSee('tidak terdaftar', false);
        $response->assertDontSee('Simpan 1 bidang');

        // Baris ketiga di Excel: satu baris judul, satu baris data yang benar.
        $response->assertSee('Baris 3');

        $this->actingAs($this->operator())
            ->post(route('bidang.impor.simpan'))
            ->assertRedirect(route('bidang.impor.form'))
            ->assertSessionHas('gagal');

        $this->assertDatabaseCount('bidang', 0);
    }

    public function test_nomor_urut_kembar_di_dalam_berkas_terdeteksi(): void
    {
        $instansi = Instansi::factory()->create();

        $response = $this->unggah([
            $this->baris($instansi, 'HP-030', 'Aset Pertama'),
            $this->baris($instansi, 'HP-030', 'Aset Kedua'),
        ]);

        $response->assertSee('muncul dua kali dalam berkas ini');
        $this->assertDatabaseCount('bidang', 0);
    }

    public function test_nomor_urut_yang_sudah_ada_ditolak_selama_mode_perbarui_mati(): void
    {
        $instansi = Instansi::factory()->create();
        Bidang::factory()->create(['nomor_urut' => 'HP-040', 'instansi_id' => $instansi->id]);

        $response = $this->unggah([$this->baris($instansi, 'HP-040', 'Nama Baru')]);

        $response->assertSee('sudah terdaftar', false);
        $this->assertDatabaseCount('bidang', 1);
        $this->assertDatabaseMissing('bidang', ['nama_aset' => 'Nama Baru']);
    }

    public function test_mode_perbarui_menimpa_bidang_yang_sudah_ada(): void
    {
        $instansi = Instansi::factory()->create();
        $lama = Bidang::factory()->create([
            'nomor_urut' => 'HP-050',
            'nama_aset' => 'Nama Lama',
            'instansi_id' => $instansi->id,
        ]);

        $this->unggah([$this->baris($instansi, 'HP-050', 'Nama Baru')], perbarui: true)
            ->assertOk()
            ->assertSee('Perbarui');

        $this->actingAs($this->operator())
            ->post(route('bidang.impor.simpan'))
            ->assertRedirect(route('bidang.index'));

        $this->assertDatabaseCount('bidang', 1);
        $this->assertSame('Nama Baru', $lama->fresh()->nama_aset);
    }

    public function test_tanggal_terbaca_baik_dari_teks_maupun_angka_seri_excel(): void
    {
        $instansi = Instansi::factory()->create();
        $tahapan = Tahapan::semua();

        $baris = $this->baris($instansi, 'HP-060', 'Aset Bertanggal');
        $baris[$this->kolomKe($tahapan[0]->kolom)] = TanggalExcel::PHPToExcel('2026-03-05');
        $baris[$this->kolomKe($tahapan[1]->kolom)] = '17 Agu 2026';
        $baris[$this->kolomKe($tahapan[2]->kolom)] = '1/9/2026';

        $this->unggah([$baris])->assertOk()->assertSee('Simpan 1 bidang');

        $this->actingAs($this->operator())->post(route('bidang.impor.simpan'));

        $bidang = Bidang::query()->firstOrFail();

        $this->assertSame('2026-03-05', $bidang->{$tahapan[0]->kolom}->format('Y-m-d'));
        $this->assertSame('2026-08-17', $bidang->{$tahapan[1]->kolom}->format('Y-m-d'));
        $this->assertSame('2026-09-01', $bidang->{$tahapan[2]->kolom}->format('Y-m-d'));
    }

    public function test_tanggal_yang_tidak_terbaca_dilaporkan_sebagai_galat(): void
    {
        $instansi = Instansi::factory()->create();

        $baris = $this->baris($instansi, 'HP-070', 'Aset Tanggal Rusak');
        $baris[$this->kolomKe(Tahapan::pertama()->kolom)] = 'kemarin sore';

        $this->unggah([$baris])
            ->assertSee('berisi &quot;kemarin sore&quot;', false)
            ->assertSee('Tulis tanggal sebagai', false)
            ->assertDontSee('Simpan 1 bidang');
    }

    public function test_berkas_tanpa_kolom_wajib_ditolak_dengan_pesan_jelas(): void
    {
        $instansi = Instansi::factory()->create();

        $judul = KolomImpor::judul();
        $baris = $this->baris($instansi, 'HP-080', 'Aset Uji');

        // Buang kolom "Nomor urut" dari berkas.
        array_shift($judul);
        array_shift($baris);

        $this->actingAs($this->operator())
            ->post(route('bidang.impor.pratinjau'), ['berkas' => $this->berkas([$judul, $baris])])
            ->assertRedirect()
            ->assertSessionHas('gagal', fn (string $pesan): bool => str_contains($pesan, 'Nomor urut'));
    }

    public function test_urutan_kolom_boleh_berbeda_dan_kolom_asing_diabaikan(): void
    {
        $instansi = Instansi::factory()->create();

        // Judul dibalik, lalu dibubuhi satu kolom yang tidak dikenal —
        // meniru berkas hasil Export Excel yang membawa kolom turunan.
        $judul = array_reverse(KolomImpor::judul());
        $judul[] = 'Progres (%)';

        $baris = array_reverse($this->baris($instansi, 'HP-090', 'Aset Terbalik'));
        $baris[] = '42';

        $this->actingAs($this->operator())
            ->post(route('bidang.impor.pratinjau'), ['berkas' => $this->berkas([$judul, $baris])])
            ->assertOk()
            ->assertSee('Simpan 1 bidang');
    }

    public function test_berkas_hanya_berisi_judul_ditolak(): void
    {
        $this->actingAs($this->operator())
            ->post(route('bidang.impor.pratinjau'), ['berkas' => $this->berkas([KolomImpor::judul()])])
            ->assertRedirect()
            ->assertSessionHas('gagal');
    }

    public function test_berkas_selain_excel_ditolak_validasi(): void
    {
        $this->actingAs($this->operator())
            ->post(route('bidang.impor.pratinjau'), ['berkas' => UploadedFile::fake()->create('data.pdf', 10)])
            ->assertSessionHasErrors('berkas');
    }

    public function test_batal_membuang_berkas_titipan(): void
    {
        $instansi = Instansi::factory()->create();

        $this->unggah([$this->baris($instansi, 'HP-100', 'Aset Batal')])->assertOk();

        $this->assertNotEmpty(Storage::files('impor-bidang'));

        $this->actingAs($this->operator())
            ->post(route('bidang.impor.batal'))
            ->assertRedirect(route('bidang.impor.form'));

        $this->assertEmpty(Storage::files('impor-bidang'));
        $this->assertDatabaseCount('bidang', 0);
    }

    /**
     * @param  list<array<int, mixed>>  $baris
     * @return TestResponse<Response>
     */
    private function unggah(array $baris, bool $perbarui = false): TestResponse
    {
        return $this->actingAs($this->operator())->post(route('bidang.impor.pratinjau'), [
            'berkas' => $this->berkas(array_merge([KolomImpor::judul()], $baris)),
            'perbarui' => $perbarui ? '1' : null,
        ]);
    }

    /**
     * Satu baris data lengkap sesuai urutan kolom template.
     *
     * @return array<int, mixed>
     */
    private function baris(Instansi $instansi, string $nomorUrut, string $namaAset): array
    {
        $isi = [
            'nomor_urut' => $nomorUrut,
            'nama_aset' => $namaAset,
            'instansi' => $instansi->nama,
            'desa' => 'Desa Uji',
            'kecamatan' => 'Kecamatan Uji',
            'luas_m2' => '1.250,75',
            'tahun_target' => '2026',
        ];

        $baris = [];

        foreach (KolomImpor::semua() as $kolom) {
            $baris[] = $isi[$kolom->kunci] ?? null;
        }

        return $baris;
    }

    /**
     * Posisi sebuah kolom pada baris, supaya test tidak menghitung sendiri
     * urutan tahap dari config.
     */
    private function kolomKe(string $kunci): int
    {
        foreach (KolomImpor::semua() as $nomor => $kolom) {
            if ($kolom->kunci === $kunci) {
                return $nomor;
            }
        }

        $this->fail("Kolom [{$kunci}] tidak ada pada definisi KolomImpor.");
    }

    /**
     * @param  list<array<int, mixed>>  $baris
     */
    private function berkas(array $baris, string $nama = 'impor.xlsx'): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($baris, null, 'A1', true);

        $sementara = (string) tempnam(sys_get_temp_dir(), 'impor');
        $path = $sementara.'.xlsx';

        @unlink($sementara);
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, $nama, null, null, true);
    }

    /**
     * Satu operator saja sepanjang satu test: unggah dan konfirmasi adalah dua
     * permintaan berurutan dari orang yang sama.
     */
    private function operator(): User
    {
        return $this->operator ??= User::factory()->peran(Peran::Operator)->create();
    }
}
