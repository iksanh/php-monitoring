<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Peran;
use App\Enums\StatusBidang;
use App\Enums\StatusTahap;
use App\Exports\BidangExport;
use App\Models\Bidang;
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
        Bidang::factory()->create(['nomor_urut' => 'HP-IKUT', 'status' => StatusBidang::Terkendala]);
        Bidang::factory()->create(['nomor_urut' => 'HP-TIDAK', 'status' => StatusBidang::Proses]);

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
        $kondisional = null;

        foreach ($tahapan as $tahap) {
            if ($tahap->kondisional()) {
                $kondisional = $tahap;

                break;
            }
        }

        $bidang = Bidang::factory()->create([
            'nomor_urut' => 'HP-UJI',
            $tahapan[0]->kolom => '2026-01-05',
            $kondisional?->kolomStatus => StatusTahap::TidakBerlaku->value,
        ]);

        $baris = (new BidangExport(new FilterBidang))->map($bidang->refresh());

        $this->assertContains('HP-UJI', $baris);
        $this->assertContains('05 Jan 2026', $baris);
        $this->assertContains('tidak berlaku', $baris, 'Tahap yang tidak berlaku harus ditandai, bukan dibiarkan kosong.');
        $this->assertContains($bidang->tahapBerikut?->label, $baris);
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
