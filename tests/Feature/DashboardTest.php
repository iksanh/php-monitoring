<?php

namespace Tests\Feature;

use App\Enums\KategoriKendala;
use App\Models\Bidang;
use App\Models\Kendala;
use App\Models\User;
use App\Services\DashboardService;
use App\Support\Tahapan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_dashboard_menampilkan_angka_grafik_dan_tabel(): void
    {
        $this->seed();

        $halaman = $this->actingAs(User::factory()->create())->get(route('dashboard'));

        $halaman->assertOk()
            ->assertSee('Capaian terhadap target')
            ->assertSee('Bidang tertahan di tiap tahap')
            ->assertSee('Capaian per instansi pemilik aset')
            ->assertSee('Sepuluh bidang terlama belum selesai')
            ->assertSee('Bidang terkendala menurut kategori');

        // Label sumbu grafik dibaca dari config, bukan ditulis di Blade. Yang
        // dipakai label_menunggu, sebab grafik menyebut kondisi berjalan —
        // lihat docs/spec.md bagian 6.
        foreach (Tahapan::semua() as $tahap) {
            $halaman->assertSee($tahap->labelMenunggu);
        }
    }

    public function test_dashboard_merinci_bidang_terkendala_per_kategori(): void
    {
        $bidang = Bidang::factory()->tahunTarget((int) date('Y'))->create();
        Kendala::factory()->kategori(KategoriKendala::Sengketa)->create(['bidang_id' => $bidang->id]);

        $halaman = $this->actingAs(User::factory()->create())->get(route('dashboard'));

        $halaman->assertOk();

        foreach (KategoriKendala::cases() as $kategori) {
            $halaman->assertSee($kategori->label());
        }
    }

    public function test_dashboard_menandai_data_yang_lama_tidak_dimutakhirkan(): void
    {
        $bidang = Bidang::factory()->create();
        $bidang->forceFill([
            'updated_at' => now()->subDays(DashboardService::BATAS_BASI_HARI + 1),
        ])->saveQuietly();

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data belum dimutakhirkan sejak');
    }

    public function test_dashboard_tanpa_data_tetap_terbuka(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tidak ada bidang yang tertunda.');
    }
}
