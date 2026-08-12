<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Bidang;
use App\Models\Instansi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Matriks hak akses: siapa boleh membuka dan mengubah apa.
 */
class PeranAksesTest extends TestCase
{
    use RefreshDatabase;

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->get(route('bidang.index'))->assertRedirect(route('login'));
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_registrasi_mandiri_ditutup(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Penyusup',
            'email' => 'penyusup@example.test',
            'password' => 'password-panjang-sekali',
            'password_confirmation' => 'password-panjang-sekali',
        ])->assertNotFound();

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'penyusup@example.test']);
    }

    /**
     * Keterbukaan lintas instansi adalah tujuan aplikasi: viewer melihat
     * seluruh data, termasuk bidang milik instansi lain.
     */
    public function test_viewer_boleh_membaca_seluruh_bidang_lintas_instansi(): void
    {
        $bidang = Bidang::factory()->create();
        $viewer = $this->pengguna(Peran::Viewer, Instansi::factory()->create());

        $this->actingAs($viewer)->get(route('bidang.index'))->assertOk();
        $this->actingAs($viewer)->get(route('bidang.show', $bidang))->assertOk()
            ->assertSee($bidang->nama_aset);
    }

    public function test_viewer_tidak_boleh_menulis(): void
    {
        $bidang = Bidang::factory()->create();
        $viewer = $this->pengguna(Peran::Viewer);

        $this->actingAs($viewer)->get(route('bidang.create'))->assertForbidden();
        $this->actingAs($viewer)->get(route('bidang.edit', $bidang))->assertForbidden();
        $this->actingAs($viewer)->delete(route('bidang.destroy', $bidang))->assertForbidden();
    }

    public function test_operator_boleh_menulis_bidang_tetapi_bukan_master_data(): void
    {
        $operator = $this->pengguna(Peran::Operator);

        $this->actingAs($operator)->get(route('bidang.create'))->assertOk();

        $this->actingAs($operator)->get(route('instansi.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('pengguna.index'))->assertForbidden();
    }

    public function test_admin_boleh_membuka_master_data(): void
    {
        $admin = $this->pengguna(Peran::Admin);

        $this->actingAs($admin)->get(route('instansi.index'))->assertOk();
        $this->actingAs($admin)->get(route('pengguna.index'))->assertOk();
        $this->actingAs($admin)->get(route('bidang.create'))->assertOk();
    }

    public function test_menu_master_data_hanya_muncul_untuk_admin(): void
    {
        $this->actingAs($this->pengguna(Peran::Admin))
            ->get(route('bidang.index'))
            ->assertSee(route('instansi.index'))
            ->assertSee(route('pengguna.index'));

        $this->actingAs($this->pengguna(Peran::Operator))
            ->get(route('bidang.index'))
            ->assertDontSee(route('instansi.index'))
            ->assertDontSee(route('pengguna.index'));
    }

    public function test_middleware_role_menolak_peran_lain(): void
    {
        $this->actingAs($this->pengguna(Peran::Viewer))
            ->get(route('instansi.create'))
            ->assertForbidden();
    }

    private function pengguna(Peran $peran, ?Instansi $instansi = null): User
    {
        return User::factory()->peran($peran)->create([
            'instansi_id' => $instansi?->id,
        ]);
    }
}
