<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Peran;
use App\Enums\StatusBidang;
use App\Models\Bidang;
use App\Models\Instansi;
use App\Models\Kendala;
use App\Models\User;
use App\Support\Tahapan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BidangCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_menambah_bidang(): void
    {
        $instansi = Instansi::factory()->create();

        $this->actingAs($this->operator())
            ->post(route('bidang.store'), $this->data($instansi))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bidang', [
            'nomor_urut' => 'HP-UJI-1',
            'nama_aset' => 'Kantor Kecamatan Uji',
            'instansi_id' => $instansi->id,
        ]);
    }

    public function test_form_tambah_menampilkan_seluruh_tahap_dari_config(): void
    {
        $halaman = $this->actingAs($this->operator())->get(route('bidang.create'));

        $halaman->assertOk();

        foreach (Tahapan::semua() as $tahap) {
            $halaman->assertSee($tahap->label)
                ->assertSee($tahap->unit)
                ->assertSee($tahap->dokumen)
                ->assertSee('name="'.$tahap->kolom.'"', escape: false);
        }
    }

    /**
     * Operator harus bebas melewati tahap — tidak ada validasi urutan.
     */
    public function test_tanggal_boleh_diisi_melompati_tahap(): void
    {
        $instansi = Instansi::factory()->create();
        $tahapan = Tahapan::semua();

        $data = $this->data($instansi);
        $data[$tahapan[0]->kolom] = '2026-01-05';
        $data[$tahapan[3]->kolom] = '2026-04-05';

        $this->actingAs($this->operator())
            ->post(route('bidang.store'), $data)
            ->assertSessionHasNoErrors();

        $bidang = Bidang::query()->where('nomor_urut', 'HP-UJI-1')->firstOrFail();

        $this->assertSame($tahapan[3]->kolom, $bidang->tahapAktif?->kolom);
        $this->assertNull($bidang->tanggalTahap($tahapan[1]));
    }

    /**
     * Status turunan tanggal tahap, bukan isian operator — docs/spec.md
     * bagian 3.
     */
    public function test_status_mengikuti_tanggal_tahap_yang_disimpan(): void
    {
        $instansi = Instansi::factory()->create();

        $this->actingAs($this->operator())
            ->post(route('bidang.store'), $this->data($instansi))
            ->assertSessionHasNoErrors();

        $bidang = Bidang::query()->where('nomor_urut', 'HP-UJI-1')->firstOrFail();
        $this->assertSame(StatusBidang::Proses, $bidang->status);

        $data = $this->data($instansi);
        $data[Bidang::KOLOM_TERBIT] = '2026-05-05';

        $this->actingAs($this->operator())
            ->put(route('bidang.update', $bidang), $data)
            ->assertSessionHasNoErrors();

        $this->assertSame(StatusBidang::Selesai, $bidang->refresh()->status);

        $data[Bidang::KOLOM_SERAH_TERIMA] = '2026-06-05';

        $this->actingAs($this->operator())
            ->put(route('bidang.update', $bidang), $data)
            ->assertSessionHasNoErrors();

        $this->assertSame(StatusBidang::Diserahkan, $bidang->refresh()->status);

        $this->actingAs($this->operator())
            ->get(route('bidang.show', $bidang))
            ->assertOk()
            ->assertSee('Sudah diserahkan');
    }

    public function test_status_yang_dikirim_dari_form_diabaikan(): void
    {
        $instansi = Instansi::factory()->create();

        $data = $this->data($instansi);
        $data['status'] = StatusBidang::Diserahkan->value;

        $this->actingAs($this->operator())
            ->post(route('bidang.store'), $data)
            ->assertSessionHasNoErrors();

        $bidang = Bidang::query()->where('nomor_urut', 'HP-UJI-1')->firstOrFail();

        $this->assertSame(StatusBidang::Proses, $bidang->status);
    }

    public function test_form_tidak_lagi_punya_isian_status(): void
    {
        $this->actingAs($this->operator())
            ->get(route('bidang.create'))
            ->assertOk()
            ->assertDontSee('name="status"', escape: false);
    }

    public function test_nomor_urut_harus_unik(): void
    {
        $instansi = Instansi::factory()->create();
        Bidang::factory()->create(['nomor_urut' => 'HP-UJI-1']);

        $this->actingAs($this->operator())
            ->post(route('bidang.store'), $this->data($instansi))
            ->assertSessionHasErrors('nomor_urut');
    }

    public function test_operator_mengubah_bidang(): void
    {
        $bidang = Bidang::factory()->create();
        $data = $this->data($bidang->instansi);
        $data['nomor_urut'] = $bidang->nomor_urut;
        $data['nama_aset'] = 'Nama Aset Baru';

        $this->actingAs($this->operator())
            ->put(route('bidang.update', $bidang), $data)
            ->assertRedirect(route('bidang.show', $bidang))
            ->assertSessionHasNoErrors();

        $this->assertSame('Nama Aset Baru', $bidang->refresh()->nama_aset);
    }

    public function test_menghapus_bidang_hanya_mengarsipkan(): void
    {
        $bidang = Bidang::factory()->create();

        $this->actingAs($this->operator())
            ->delete(route('bidang.destroy', $bidang))
            ->assertRedirect(route('bidang.index'));

        $this->assertSoftDeleted($bidang);
    }

    public function test_halaman_detail_menampilkan_timeline_lengkap_dan_kendala(): void
    {
        $bidang = Bidang::factory()->sampaiTahap(3)->create();
        $kendala = Kendala::factory()->create(['bidang_id' => $bidang->id]);

        $halaman = $this->actingAs($this->operator())->get(route('bidang.show', $bidang));

        $halaman->assertOk()
            ->assertSee($bidang->nama_aset)
            ->assertSee($bidang->desa)
            ->assertSee($bidang->kecamatan)
            ->assertSee($kendala->uraian);

        // Tiap tahap menampilkan label, unit pelaksana, dan dokumen dasarnya.
        foreach (Tahapan::semua() as $tahap) {
            $halaman->assertSee($tahap->label)
                ->assertSee($tahap->unit)
                ->assertSee($tahap->dokumen);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Instansi $instansi): array
    {
        return [
            'nomor_urut' => 'HP-UJI-1',
            'nama_aset' => 'Kantor Kecamatan Uji',
            'instansi_id' => $instansi->id,
            'penggunaan' => 'Kantor',
            'desa' => 'Sukamaju',
            'kecamatan' => 'Kota Utara',
            'luas_m2' => 1250.5,
            'nomor_berkas_kkp' => '123/HP/2026',
            'tahun_target' => 2026,
            'keterangan' => null,
        ];
    }

    private function operator(): User
    {
        return User::factory()->peran(Peran::Operator)->create();
    }
}
