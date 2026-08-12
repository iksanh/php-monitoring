<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Bidang;
use App\Models\Instansi;
use App\Models\JenisInstansi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_menambah_instansi(): void
    {
        $this->actingAs($this->admin())
            ->post(route('instansi.store'), [
                'nama' => 'Dinas Pendidikan',
                'jenis_instansi_id' => JenisInstansi::factory()->create()->id,
                'aktif' => '1',
            ])
            ->assertRedirect(route('instansi.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('instansi', ['nama' => 'Dinas Pendidikan', 'aktif' => true]);
    }

    public function test_instansi_yang_masih_dipakai_bidang_tidak_bisa_dihapus(): void
    {
        $bidang = Bidang::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('instansi.destroy', $bidang->instansi))
            ->assertForbidden();

        $this->assertDatabaseHas('instansi', ['id' => $bidang->instansi_id]);
    }

    public function test_instansi_kosong_bisa_dihapus(): void
    {
        $instansi = Instansi::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('instansi.destroy', $instansi))
            ->assertRedirect(route('instansi.index'));

        $this->assertDatabaseMissing('instansi', ['id' => $instansi->id]);
    }

    public function test_admin_membuat_pengguna_baru_dengan_peran(): void
    {
        $instansi = Instansi::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('pengguna.store'), [
                'name' => 'Operator Baru',
                'email' => 'operator.baru@example.test',
                'role' => Peran::Operator->value,
                'instansi_id' => $instansi->id,
                'password' => 'kata-sandi-yang-panjang',
                'password_confirmation' => 'kata-sandi-yang-panjang',
            ])
            ->assertRedirect(route('pengguna.index'))
            ->assertSessionHasNoErrors();

        $pengguna = User::query()->where('email', 'operator.baru@example.test')->firstOrFail();

        $this->assertSame(Peran::Operator, $pengguna->role);
        $this->assertSame($instansi->id, $pengguna->instansi_id);
        $this->assertNotNull($pengguna->email_verified_at);
        $this->assertTrue(Hash::check('kata-sandi-yang-panjang', $pengguna->password));
    }

    public function test_mengubah_pengguna_tanpa_mengganti_kata_sandi(): void
    {
        $pengguna = User::factory()->peran(Peran::Viewer)->create();
        $sandiLama = $pengguna->password;

        $this->actingAs($this->admin())
            ->put(route('pengguna.update', $pengguna), [
                'name' => 'Nama Diperbarui',
                'email' => $pengguna->email,
                'role' => Peran::Operator->value,
                'instansi_id' => null,
                'password' => null,
            ])
            ->assertSessionHasNoErrors();

        $pengguna->refresh();

        $this->assertSame('Nama Diperbarui', $pengguna->name);
        $this->assertSame(Peran::Operator, $pengguna->role);
        $this->assertSame($sandiLama, $pengguna->password);
    }

    public function test_admin_tidak_bisa_menghapus_akunnya_sendiri(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete(route('pengguna.destroy', $admin))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_terakhir_tidak_bisa_dihapus(): void
    {
        $admin = $this->admin();
        $adminLain = User::factory()->peran(Peran::Admin)->create();

        // Dua admin: yang satu masih boleh dihapus.
        $this->actingAs($admin)
            ->delete(route('pengguna.destroy', $adminLain))
            ->assertRedirect(route('pengguna.index'));

        // Kini tinggal satu admin, dan admin tidak boleh menghapus dirinya.
        $this->actingAs($admin)
            ->delete(route('pengguna.destroy', $admin))
            ->assertForbidden();
    }

    private function admin(): User
    {
        return User::factory()->peran(Peran::Admin)->create();
    }
}
