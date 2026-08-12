<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KategoriKendala;
use App\Enums\Peran;
use App\Enums\StatusBidang;
use App\Exports\BidangExport;
use App\Models\Bidang;
use App\Models\Kendala;
use App\Models\User;
use App\Support\Filter\FilterBidang;
use App\Support\Tahapan;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ExportBidangTest extends TestCase
{
    use RefreshDatabase;

    public function test_berkas_excel_benar_benar_terbentuk(): void
    {
        Bidang::factory()->count(3)->create();

        $response = $this->masuk()->get(route('bidang.export'));

        $response->assertOk();
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));
        $this->assertNotEmpty($response->streamedContent());
    }

    public function test_judul_kolom_mengikuti_urutan_config_tahapan(): void
    {
        Excel::fake();

        $this->masuk()->get(route('bidang.export'))->assertOk();

        Excel::assertDownloaded($this->namaBerkas(), function (BidangExport $export): bool {
            $judul = $export->headings();

            $posisi = [];
            foreach (Tahapan::semua() as $tahap) {
                $kunci = $tahap->urutan.'. '.$tahap->label;

                $this->assertContains($kunci, $judul, "Kolom tahap {$tahap->label} tidak ada di export.");

                $posisi[] = array_search($kunci, $judul, true);
            }

            $terurut = $posisi;
            sort($terurut);

            $this->assertSame($terurut, $posisi, 'Urutan kolom tahap tidak mengikuti config.');

            return true;
        });
    }

    public function test_export_mengikuti_filter_yang_sedang_aktif(): void
    {
        $terkendala = Bidang::factory()->create(['nomor_urut' => 'HP-IKUT']);
        Kendala::factory()->create(['bidang_id' => $terkendala->id]);

        Bidang::factory()->create(['nomor_urut' => 'HP-TIDAK']);

        Excel::fake();

        $this->masuk()->get(route('bidang.export', ['status' => StatusBidang::Terkendala->value]))->assertOk();

        Excel::assertDownloaded($this->namaBerkas(), function (BidangExport $export): bool {
            $nomor = $export->query()->pluck('nomor_urut')->all();

            $this->assertSame(['HP-IKUT'], $nomor);

            return true;
        });
    }

    public function test_baris_memuat_tanggal_tahap_dan_kolom_turunan(): void
    {
        $tahapan = Tahapan::semua();

        $bidang = Bidang::factory()->create([
            'nomor_urut' => 'HP-UJI',
            $tahapan[0]->kolom => '2026-01-05',
        ]);

        $baris = (new BidangExport(new FilterBidang))->map($bidang->refresh());

        $this->assertContains('HP-UJI', $baris);
        $this->assertContains('05 Jan 2026', $baris);
        $this->assertContains($tahapan[0]->label, $baris, 'Tahap aktif memakai label, bukan label_menunggu.');
        $this->assertContains($tahapan[1]->labelMenunggu, $baris, 'Kondisi berjalan memakai label_menunggu.');
        $this->assertContains(StatusBidang::Proses->label(), $baris);
    }

    /**
     * Header kolom tahap memakai `label`, bukan `label_menunggu`.
     */
    public function test_judul_kolom_tahap_memakai_label_selesai(): void
    {
        $judul = (new BidangExport(new FilterBidang))->headings();

        foreach (Tahapan::semua() as $tahap) {
            $this->assertContains($tahap->urutan.'. '.$tahap->label, $judul);
        }
    }

    public function test_export_dapat_disaring_menurut_kategori_kendala(): void
    {
        $sengketa = Bidang::factory()->create(['nomor_urut' => 'HP-SENGKETA']);
        Kendala::factory()->kategori(KategoriKendala::Sengketa)->create(['bidang_id' => $sengketa->id]);

        Bidang::factory()->create(['nomor_urut' => 'HP-BERSIH']);

        $nomor = (new BidangExport(new FilterBidang(kategoriKendala: KategoriKendala::Sengketa)))
            ->query()
            ->pluck('nomor_urut')
            ->all();

        $this->assertSame(['HP-SENGKETA'], $nomor);
    }

    public function test_viewer_boleh_export_tetapi_tamu_tidak(): void
    {
        Excel::fake();

        $this->masuk()->get(route('bidang.export'))->assertOk();

        auth()->logout();

        $this->get(route('bidang.export'))->assertRedirect(route('login'));
    }

    private function masuk(): self
    {
        return $this->actingAs(User::factory()->peran(Peran::Viewer)->create());
    }

    private function namaBerkas(): string
    {
        $this->freezeTime();

        return 'bidang-hak-pakai-'.CarbonImmutable::now()->format('Ymd-His').'.xlsx';
    }
}
