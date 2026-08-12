<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KategoriKendala;
use App\Models\Bidang;
use App\Models\Instansi;
use App\Models\Kendala;
use App\Services\DashboardService;
use App\Support\Tahapan;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private DashboardService $dashboard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dashboard = app(DashboardService::class);
    }

    /**
     * Penjaga aturan keras: sebaran delapan tahap harus satu query, bukan
     * delapan query terpisah.
     */
    public function test_sebaran_dihitung_dengan_satu_query_agregasi(): void
    {
        Bidang::factory()->count(5)->create();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->dashboard->sebaranTertahan();

        $log = DB::getQueryLog();

        $this->assertCount(1, $log, 'Sebaran per tahap harus dihitung dengan satu query.');
        $this->assertSame(
            Tahapan::jumlah(),
            substr_count(strtolower((string) $log[0]['query']), 'sum(case when'),
            'Query harus memuat satu SUM(CASE WHEN ...) untuk tiap tahap.'
        );
    }

    /**
     * Pembuktian inti TAHAP C: hasil query agregasi harus sama persis dengan
     * hasil accessor tahapBerikut yang dihitung di PHP, termasuk untuk bidang
     * yang tahapnya dilewati dan yang tahap kondisionalnya tidak berlaku.
     */
    public function test_sebaran_sama_dengan_perhitungan_accessor(): void
    {
        $this->seed();
        $this->bidangKasusKhusus();

        $harapan = [];
        foreach (Tahapan::semua() as $tahap) {
            $harapan[$tahap->kolom] = 0;
        }

        foreach (Bidang::query()->get() as $bidang) {
            $berikut = $bidang->tahapBerikut;

            if ($berikut !== null) {
                $harapan[$berikut->kolom]++;
            }
        }

        foreach ($this->dashboard->sebaranTertahan() as $tertahan) {
            $this->assertSame(
                $harapan[$tertahan->tahap->kolom],
                $tertahan->jumlah,
                "Jumlah tertahan pada tahap {$tertahan->tahap->label} tidak cocok dengan accessor."
            );
        }
    }

    public function test_bidang_tanpa_tanggal_tertahan_di_tahap_pertama(): void
    {
        Bidang::factory()->create();

        $sebaran = $this->sebaranPerKolom();

        $this->assertSame(1, $sebaran[Tahapan::pertama()->kolom]);
    }

    public function test_bidang_dengan_tahap_dilewati_dihitung_di_tahap_yang_benar(): void
    {
        $tahapan = Tahapan::semua();

        // Tahap 1 dan 3 terisi, tahap 2 dilewati: yang ditunggu adalah tahap 4.
        Bidang::factory()->create([
            $tahapan[0]->kolom => '2026-01-05',
            $tahapan[2]->kolom => '2026-03-05',
        ]);

        $sebaran = $this->sebaranPerKolom();

        $this->assertSame(1, $sebaran[$tahapan[3]->kolom]);
        $this->assertSame(0, $sebaran[$tahapan[1]->kolom]);
    }

    public function test_bidang_tuntas_tidak_dihitung_tertahan(): void
    {
        Bidang::factory()->tuntas()->create();

        $this->assertSame(0, array_sum($this->sebaranPerKolom()));
    }

    public function test_tertahan_dikelompokkan_per_penanggung_jawab(): void
    {
        $this->seed();

        $perPihak = $this->dashboard->tertahanPerPenanggungJawab();
        $sebaran = $this->dashboard->sebaranTertahan();

        $this->assertSame(
            array_sum(array_map(fn ($baris) => $baris->jumlah, $sebaran)),
            array_sum($perPihak),
            'Total per penanggung jawab harus sama dengan total sebaran tahap.'
        );

        foreach ($perPihak as $jumlah) {
            $this->assertGreaterThanOrEqual(0, $jumlah);
        }
    }

    public function test_kartu_angka_hanya_menghitung_tahun_target_berjalan(): void
    {
        $tahun = (int) date('Y');

        Bidang::factory()->tahunTarget($tahun)->count(3)->create();

        // Sertipikat terbit, aset belum diserahkan → selesai.
        Bidang::factory()->tahunTarget($tahun)->sampaiTahap(Tahapan::jumlah() - 1)->create();

        // Seluruh tahap terisi → sudah diserahkan.
        Bidang::factory()->tahunTarget($tahun)->tuntas()->create();

        // Punya kendala terbuka → terkendala, apa pun tanggalnya.
        $terkendala = Bidang::factory()->tahunTarget($tahun)->create();
        Kendala::factory()->create(['bidang_id' => $terkendala->id]);

        Bidang::factory()->tahunTarget($tahun - 1)->count(4)->create();

        $kartu = $this->dashboard->kartuAngka($tahun);

        $this->assertSame(6, $kartu->total);
        $this->assertSame(2, $kartu->bersertipikat);
        $this->assertSame(3, $kartu->proses);
        $this->assertSame(1, $kartu->diserahkan);
        $this->assertSame(1, $kartu->terkendala);
        $this->assertSame(33, $kartu->persenCapaian());
    }

    public function test_kartu_angka_tanpa_data_tidak_membagi_nol(): void
    {
        $kartu = $this->dashboard->kartuAngka((int) date('Y'));

        $this->assertSame(0, $kartu->total);
        $this->assertSame(0, $kartu->persenCapaian());
    }

    public function test_capaian_per_instansi(): void
    {
        $tahun = (int) date('Y');
        $pemda = Instansi::factory()->create(['nama' => 'Aaa Pemda']);
        $kejaksaan = Instansi::factory()->create(['nama' => 'Bbb Kejaksaan']);

        Bidang::factory()->tahunTarget($tahun)->tuntas()->create(['instansi_id' => $pemda->id]);
        Bidang::factory()->tahunTarget($tahun)->count(3)->create(['instansi_id' => $pemda->id]);
        Bidang::factory()->tahunTarget($tahun)->create(['instansi_id' => $kejaksaan->id]);

        $capaian = $this->dashboard->capaianPerInstansi($tahun);

        $this->assertCount(2, $capaian);
        $this->assertSame('Aaa Pemda', $capaian[0]->nama);
        $this->assertSame(4, $capaian[0]->total);
        $this->assertSame(1, $capaian[0]->bersertipikat);
        $this->assertSame(25, $capaian[0]->persen());
        $this->assertSame(0, $capaian[1]->persen());
    }

    public function test_bidang_terlama_diurutkan_dan_membatasi_hasil(): void
    {
        $mulai = Tahapan::pertama()->kolom;

        Bidang::factory()->create([$mulai => '2024-01-10']);
        Bidang::factory()->create([$mulai => '2026-01-10']);
        Bidang::factory()->tuntas()->create();
        Bidang::factory()->create();

        $terlama = $this->dashboard->bidangTerlama(10);

        // Bidang tuntas dan bidang tanpa tanggal permohonan tidak ikut.
        $this->assertCount(2, $terlama);
        $this->assertSame('2024-01-10', $terlama->first()?->getAttribute($mulai)->format('Y-m-d'));
    }

    public function test_bidang_terlama_tidak_menimbulkan_query_beruntun(): void
    {
        Bidang::factory()->count(12)->create([Tahapan::pertama()->kolom => '2025-01-10']);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $terlama = $this->dashboard->bidangTerlama();
        $terlama->each(function (Bidang $bidang): void {
            $bidang->instansi->nama;
            $bidang->kondisiTahap;
            $bidang->adaKendalaAktif();
        });

        // Satu query bidang + eager load instansi + eager load kendala aktif.
        $this->assertCount(3, DB::getQueryLog());
    }

    public function test_terkendala_dikelompokkan_per_kategori(): void
    {
        $tahun = (int) date('Y');

        $sengketa = Bidang::factory()->tahunTarget($tahun)->create();
        Kendala::factory()->kategori(KategoriKendala::Sengketa)->create(['bidang_id' => $sengketa->id]);

        // Dua kendala sekategori pada satu bidang tetap terhitung satu bidang.
        $hutan = Bidang::factory()->tahunTarget($tahun)->create();
        Kendala::factory()->count(2)->kategori(KategoriKendala::KawasanHutan)
            ->create(['bidang_id' => $hutan->id]);

        // Kendala yang sudah ditutup tidak dihitung.
        $tutup = Bidang::factory()->tahunTarget($tahun)->create();
        Kendala::factory()->kategori(KategoriKendala::Sengketa)->selesai()
            ->create(['bidang_id' => $tutup->id]);

        // Tahun target lain tidak ikut.
        $tahunLalu = Bidang::factory()->tahunTarget($tahun - 1)->create();
        Kendala::factory()->kategori(KategoriKendala::Sengketa)->create(['bidang_id' => $tahunLalu->id]);

        $rincian = $this->dashboard->terkendalaPerKategori($tahun);

        $this->assertSame(1, $rincian[KategoriKendala::Sengketa->value]);
        $this->assertSame(1, $rincian[KategoriKendala::KawasanHutan->value]);
        $this->assertSame(0, $rincian[KategoriKendala::BerkasKurang->value]);

        // Seluruh kategori tetap muncul supaya rinciannya terbaca lengkap.
        $this->assertSame(
            array_column(KategoriKendala::cases(), 'value'),
            array_keys($rincian)
        );
    }

    public function test_penanda_data_basi(): void
    {
        $this->assertTrue($this->dashboard->dataBasi(null));
        $this->assertTrue($this->dashboard->dataBasi(CarbonImmutable::now()->subDays(15)));
        $this->assertFalse($this->dashboard->dataBasi(CarbonImmutable::now()->subDays(3)));
    }

    public function test_pemutakhiran_terakhir_mengambil_yang_paling_baru(): void
    {
        $this->assertNull($this->dashboard->pemutakhiranTerakhir());

        Bidang::factory()->create();

        $this->assertNotNull($this->dashboard->pemutakhiranTerakhir());
    }

    /**
     * Kasus yang paling mudah salah bila query dirakit asal-asalan.
     */
    private function bidangKasusKhusus(): void
    {
        $tahapan = Tahapan::semua();

        // Tanpa tanggal sama sekali.
        Bidang::factory()->create();

        // Melewati satu tahap.
        Bidang::factory()->create([
            $tahapan[0]->kolom => '2026-01-05',
            $tahapan[2]->kolom => '2026-03-05',
        ]);

        // Hanya tahap terakhir yang terisi — urutan tidak dipaksakan.
        Bidang::factory()->create([
            $tahapan[Tahapan::jumlah() - 1]->kolom => '2026-04-05',
        ]);

        // Tuntas.
        Bidang::factory()->tuntas()->create();
    }

    /**
     * @return array<string, int>
     */
    private function sebaranPerKolom(): array
    {
        $hasil = [];

        foreach ($this->dashboard->sebaranTertahan() as $tertahan) {
            $hasil[$tertahan->tahap->kolom] = $tertahan->jumlah;
        }

        return $hasil;
    }
}
