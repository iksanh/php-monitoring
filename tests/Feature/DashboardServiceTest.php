<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusBidang;
use App\Enums\StatusTahap;
use App\Models\Bidang;
use App\Models\Instansi;
use App\Services\DashboardService;
use App\Support\Tahap;
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

    public function test_tahap_tidak_berlaku_dilewati_dalam_sebaran(): void
    {
        $kondisional = $this->tahapKondisional();
        $tahapan = Tahapan::semua();
        $sebelum = $tahapan[$kondisional->urutan - 2];
        $sesudah = $tahapan[$kondisional->urutan];

        // Seluruh tahap sebelum tahap kondisional terisi, tahap kondisional
        // dinyatakan tidak berlaku: yang ditunggu adalah tahap sesudahnya.
        $atribut = [$kondisional->kolomStatus => StatusTahap::TidakBerlaku->value];

        foreach ($tahapan as $tahap) {
            if ($tahap->urutan > $sebelum->urutan) {
                break;
            }

            $atribut[$tahap->kolom] = '2026-0'.$tahap->urutan.'-05';
        }

        Bidang::factory()->create($atribut);

        $sebaran = $this->sebaranPerKolom();

        $this->assertSame(1, $sebaran[$sesudah->kolom]);
        $this->assertSame(0, $sebaran[$kondisional->kolom]);
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
        Bidang::factory()->tahunTarget($tahun)->tuntas()->create();
        Bidang::factory()->tahunTarget($tahun)->tuntas()->status(StatusBidang::Diserahkan)->create();
        Bidang::factory()->tahunTarget($tahun)->status(StatusBidang::Terkendala)->create();
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
        $terlama->each(fn (Bidang $bidang) => $bidang->instansi->nama);

        // Satu query bidang + satu query eager load instansi.
        $this->assertCount(2, DB::getQueryLog());
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
        $kondisional = $this->tahapKondisional();

        // Tanpa tanggal sama sekali.
        Bidang::factory()->create();

        // Melewati satu tahap.
        Bidang::factory()->create([
            $tahapan[0]->kolom => '2026-01-05',
            $tahapan[2]->kolom => '2026-03-05',
        ]);

        // Tahap kondisional tidak berlaku, tahap sebelumnya sudah terisi.
        Bidang::factory()->create([
            $kondisional->kolomStatus => StatusTahap::TidakBerlaku->value,
            $tahapan[0]->kolom => '2026-01-05',
        ]);

        // Tahap kondisional tidak berlaku tetapi tanggalnya terlanjur terisi:
        // tanggal itu harus diabaikan.
        Bidang::factory()->create([
            $kondisional->kolomStatus => StatusTahap::TidakBerlaku->value,
            $kondisional->kolom => '2026-02-05',
            $tahapan[0]->kolom => '2026-01-05',
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

    private function tahapKondisional(): Tahap
    {
        foreach (Tahapan::semua() as $tahap) {
            if ($tahap->kondisional()) {
                return $tahap;
            }
        }

        $this->markTestSkipped('config/tahapan.php tidak punya tahap kondisional.');
    }
}
