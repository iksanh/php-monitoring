<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Instansi;
use App\Models\JenisInstansi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JenisInstansiCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_menambah_jenis_baru(): void
    {
        $this->actingAs($this->admin())
            ->post(route('jenis-instansi.store'), [
                'nama' => 'Badan Usaha Milik Daerah',
                'aktif' => '1',
            ])
            ->assertRedirect(route('jenis-instansi.index'))
            ->assertSessionHasNoErrors();

        $jenis = JenisInstansi::query()->where('nama', 'Badan Usaha Milik Daerah')->firstOrFail();

        $this->assertTrue($jenis->aktif);
        $this->assertSame('badan_usaha_milik_daerah', $jenis->kode, 'Kode dibuat otomatis dari nama.');
    }

    public function test_jenis_baru_langsung_bisa_dipakai_saat_menambah_instansi(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('jenis-instansi.store'), ['nama' => 'Kementerian', 'aktif' => '1'])
            ->assertSessionHasNoErrors();

        $jenis = JenisInstansi::query()->where('nama', 'Kementerian')->firstOrFail();

        $this->actingAs($admin)->get(route('instansi.create'))
            ->assertOk()
            ->assertSee('Kementerian');

        $this->actingAs($admin)
            ->post(route('instansi.store'), [
                'nama' => 'Kantor Wilayah Kementerian ATR',
                'jenis_instansi_id' => $jenis->id,
                'aktif' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('instansi', [
            'nama' => 'Kantor Wilayah Kementerian ATR',
            'jenis_instansi_id' => $jenis->id,
        ]);
    }

    public function test_mengubah_nama_tidak_mengubah_kode(): void
    {
        $jenis = JenisInstansi::query()->create(['nama' => 'Pemerintah Daerah', 'aktif' => true]);
        $kodeAwal = $jenis->kode;

        $this->actingAs($this->admin())
            ->put(route('jenis-instansi.update', $jenis), ['nama' => 'Pemda Kabupaten', 'aktif' => '1'])
            ->assertSessionHasNoErrors();

        $jenis->refresh();

        $this->assertSame('Pemda Kabupaten', $jenis->nama);
        $this->assertSame($kodeAwal, $jenis->kode, 'Kode harus tetap supaya rujukan kode aplikasi tidak putus.');
    }

    public function test_nama_jenis_harus_unik(): void
    {
        JenisInstansi::factory()->create(['nama' => 'Kejaksaan']);

        $this->actingAs($this->admin())
            ->post(route('jenis-instansi.store'), ['nama' => 'Kejaksaan', 'aktif' => '1'])
            ->assertSessionHasErrors('nama');
    }

    public function test_kode_tetap_unik_walau_nama_mirip(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('jenis-instansi.store'), ['nama' => 'Dinas Daerah', 'aktif' => '1']);
        $this->actingAs($admin)->post(route('jenis-instansi.store'), ['nama' => 'Dinas  Daerah!', 'aktif' => '1']);

        $kode = JenisInstansi::query()->pluck('kode')->all();

        $this->assertSame($kode, array_unique($kode));

        // Tiga jenis bawaan sudah ditanam migrasi, ditambah dua yang baru.
        $baru = JenisInstansi::query()->where('nama', 'like', 'Dinas%')->pluck('kode')->all();

        $this->assertCount(2, $baru);
        $this->assertSame(['dinas_daerah', 'dinas_daerah_2'], $baru);
    }

    public function test_jenis_yang_masih_dipakai_tidak_bisa_dihapus(): void
    {
        $instansi = Instansi::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('jenis-instansi.destroy', $instansi->jenis))
            ->assertForbidden();

        $this->assertDatabaseHas('jenis_instansi', ['id' => $instansi->jenis_instansi_id]);
    }

    public function test_jenis_yang_belum_dipakai_bisa_dihapus(): void
    {
        $jenis = JenisInstansi::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('jenis-instansi.destroy', $jenis))
            ->assertRedirect(route('jenis-instansi.index'));

        $this->assertDatabaseMissing('jenis_instansi', ['id' => $jenis->id]);
    }

    public function test_jenis_nonaktif_tidak_ditawarkan_pada_form_instansi(): void
    {
        $nonaktif = JenisInstansi::factory()->nonaktif()->create(['nama' => 'Jenis Pensiun']);

        $this->actingAs($this->admin())
            ->get(route('instansi.create'))
            ->assertOk()
            ->assertDontSee($nonaktif->nama);
    }

    public function test_hanya_admin_yang_boleh_membuka_master_jenis(): void
    {
        $this->actingAs(User::factory()->peran(Peran::Operator)->create())
            ->get(route('jenis-instansi.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->peran(Peran::Viewer)->create())
            ->get(route('jenis-instansi.create'))
            ->assertForbidden();

        auth()->logout();

        $this->get(route('jenis-instansi.index'))->assertRedirect(route('login'));
    }

    private function admin(): User
    {
        return User::factory()->peran(Peran::Admin)->create();
    }
}
