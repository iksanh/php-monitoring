<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Peran;
use App\Enums\StatusBidang;
use App\Models\Bidang;
use App\Models\User;
use App\Services\DashboardService;
use App\Support\Filter\FilterBidang;
use App\Support\Tahapan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pemisahan daftar aset dari pekerjaan yang benar-benar berjalan.
 *
 * Daftar aset pemda memuat ribuan bidang; Kantor Pertanahan memilih sebagian
 * untuk diproses. Tanpa pemisahan itu seluruh aset yang belum bersertipikat
 * terhitung "dalam proses" di dashboard.
 */
class PantauanBidangTest extends TestCase
{
    use RefreshDatabase;

    public function test_bidang_antrean_berstatus_belum_diproses(): void
    {
        $bidang = Bidang::factory()->antrean()->create();

        $this->assertNull($bidang->tgl_diproses);
        $this->assertSame(StatusBidang::BelumDiproses, $bidang->status);
    }

    public function test_bidang_baru_lewat_aplikasi_langsung_dipantau(): void
    {
        $bidang = Bidang::factory()->create();

        $this->assertNotNull($bidang->tgl_diproses);
        $this->assertSame(StatusBidang::Proses, $bidang->status);
    }

    public function test_mengisi_tanggal_tahap_menandai_bidang_diproses_sendiri(): void
    {
        $bidang = Bidang::factory()->antrean()->create();

        $bidang->update([Tahapan::pertama()->kolom => '2026-02-10']);

        $this->assertNotNull($bidang->refresh()->tgl_diproses);
        $this->assertSame(StatusBidang::Proses, $bidang->status);
    }

    public function test_operator_menandai_bidang_terpilih_diproses(): void
    {
        $dipilih = Bidang::factory()->antrean()->count(2)->create();
        $dilewati = Bidang::factory()->antrean()->create();

        $this->actingAs($this->operator())
            ->post(route('bidang.proses'), [
                'bidang' => $dipilih->pluck('id')->all(),
                'tahun_target' => 2027,
            ])
            ->assertRedirect();

        foreach ($dipilih as $bidang) {
            $bidang->refresh();
            $this->assertNotNull($bidang->tgl_diproses);
            $this->assertSame(2027, $bidang->tahun_target);
            $this->assertSame(StatusBidang::Proses, $bidang->status);
        }

        $this->assertNull($dilewati->refresh()->tgl_diproses);
    }

    public function test_seluruh_hasil_penyaringan_ikut_ditandai(): void
    {
        Bidang::factory()->antrean()->count(3)->create(['pengguna_barang' => 'Dinas Kesehatan']);
        $luarSaringan = Bidang::factory()->antrean()->create(['pengguna_barang' => 'Dinas Pendidikan']);

        $this->actingAs($this->operator())
            ->post(route('bidang.proses', ['pengguna_barang' => 'Dinas Kesehatan']), ['semua_hasil' => '1'])
            ->assertRedirect();

        $this->assertSame(3, Bidang::query()->dipantau()->count());
        $this->assertNull($luarSaringan->refresh()->tgl_diproses);
    }

    public function test_tanpa_pilihan_ditolak_dengan_pesan(): void
    {
        Bidang::factory()->antrean()->create();

        $this->actingAs($this->operator())
            ->post(route('bidang.proses'), [])
            ->assertSessionHasErrors('bidang');

        $this->assertSame(0, Bidang::query()->dipantau()->count());
    }

    public function test_viewer_tidak_boleh_menandai(): void
    {
        $bidang = Bidang::factory()->antrean()->create();

        $this->actingAs(User::factory()->peran(Peran::Viewer)->create())
            ->post(route('bidang.proses'), ['bidang' => [$bidang->id]])
            ->assertForbidden();

        $this->assertNull($bidang->refresh()->tgl_diproses);
    }

    public function test_bidang_dikembalikan_ke_antrean(): void
    {
        $bidang = Bidang::factory()->create();

        $this->actingAs($this->operator())
            ->post(route('bidang.kembalikan'), ['bidang' => [$bidang->id]])
            ->assertRedirect();

        $bidang->refresh();
        $this->assertNull($bidang->tgl_diproses);
        $this->assertSame(StatusBidang::BelumDiproses, $bidang->status);
    }

    public function test_bidang_yang_tanggal_tahapnya_terisi_tidak_bisa_dikembalikan(): void
    {
        $bidang = Bidang::factory()->sampaiTahap(2)->create();

        $this->actingAs($this->operator())
            ->post(route('bidang.kembalikan'), ['bidang' => [$bidang->id]])
            ->assertRedirect();

        $this->assertNotNull($bidang->refresh()->tgl_diproses);
    }

    public function test_penyaring_pantauan_memisahkan_antrean_dari_yang_diproses(): void
    {
        Bidang::factory()->antrean()->create(['nomor_urut' => 'ANTRE-1']);
        Bidang::factory()->create(['nomor_urut' => 'PROSES-1']);

        // Aset lama yang sertipikatnya sudah terbit tanpa pernah dipantau:
        // bukan antrean, bukan pula pekerjaan berjalan.
        Bidang::factory()->antrean()->create([
            'nomor_urut' => 'RIWAYAT-1',
            Bidang::KOLOM_TERBIT => '2019-05-05',
            Bidang::KOLOM_SERAH_TERIMA => '2019-05-05',
        ]);

        $this->masuk()->get(route('bidang.index', ['pantauan' => FilterBidang::ANTREAN]))
            ->assertOk()
            ->assertSee('ANTRE-1')
            ->assertDontSee('PROSES-1')
            ->assertDontSee('RIWAYAT-1');

        $this->masuk()->get(route('bidang.index', ['pantauan' => FilterBidang::DIPANTAU]))
            ->assertOk()
            ->assertSee('PROSES-1')
            ->assertDontSee('ANTRE-1')
            ->assertDontSee('RIWAYAT-1');
    }

    public function test_dashboard_hanya_menghitung_bidang_yang_diproses(): void
    {
        $tahun = (int) now()->format('Y');

        Bidang::factory()->antrean()->count(5)->tahunTarget($tahun)->create();
        Bidang::factory()->count(2)->tahunTarget($tahun)->create();

        $dashboard = app(DashboardService::class);

        $this->assertSame(2, $dashboard->kartuAngka($tahun)->total);

        $ringkas = $dashboard->ringkasAset();
        $this->assertSame(7, $ringkas->total);
        $this->assertSame(2, $ringkas->dipantau);
        $this->assertSame(5, $ringkas->antrean);
    }

    /**
     * Sebagian catatan lama hanya menyimpan tanggal serah terima. Aset itu
     * jelas sudah bersertipikat, jadi tidak boleh masuk hitungan antrean.
     */
    public function test_serah_terima_tanpa_tanggal_sertipikat_bukan_antrean(): void
    {
        Bidang::factory()->antrean()->create([
            'nomor_urut' => 'LAMA-1',
            Bidang::KOLOM_SERAH_TERIMA => '2019-05-05',
        ]);

        $ringkas = app(DashboardService::class)->ringkasAset();

        $this->assertSame(1, $ringkas->riwayatBersertipikat);
        $this->assertSame(0, $ringkas->antrean);

        $this->masuk()->get(route('bidang.index', ['pantauan' => FilterBidang::ANTREAN]))
            ->assertOk()
            ->assertDontSee('LAMA-1');
    }

    public function test_aset_bersertipikat_yang_belum_dipantau_terhitung_riwayat(): void
    {
        Bidang::factory()->antrean()->create([
            Bidang::KOLOM_TERBIT => '2019-05-05',
            Bidang::KOLOM_SERAH_TERIMA => '2019-05-05',
        ]);

        $ringkas = app(DashboardService::class)->ringkasAset();

        $this->assertSame(1, $ringkas->riwayatBersertipikat);
        $this->assertSame(0, $ringkas->antrean);
        $this->assertSame(0, $ringkas->dipantau);
    }

    private function operator(): User
    {
        return User::factory()->peran(Peran::Operator)->create();
    }

    private function masuk(): self
    {
        return $this->actingAs(User::factory()->peran(Peran::Viewer)->create());
    }
}
