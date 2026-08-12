<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Peran;
use App\Enums\StatusBidang;
use App\Enums\StatusTahap;
use App\Models\Bidang;
use App\Models\Instansi;
use App\Models\Kendala;
use App\Models\User;
use App\Support\Tahap;
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

    public function test_tahap_kondisional_dapat_dinyatakan_tidak_berlaku(): void
    {
        $instansi = Instansi::factory()->create();
        $kondisional = $this->tahapKondisional();

        $data = $this->data($instansi);
        $data[$kondisional->kolomStatus] = StatusTahap::TidakBerlaku->value;

        $this->actingAs($this->operator())
            ->post(route('bidang.store'), $data)
            ->assertSessionHasNoErrors();

        $bidang = Bidang::query()->where('nomor_urut', 'HP-UJI-1')->firstOrFail();

        $this->assertCount(Tahapan::jumlah() - 1, $bidang->tahapBerlaku());
    }

    public function test_status_sudah_diserahkan_dapat_disimpan_dan_ditampilkan(): void
    {
        $instansi = Instansi::factory()->create();

        $data = $this->data($instansi);
        $data['status'] = StatusBidang::Diserahkan->value;

        $this->actingAs($this->operator())
            ->post(route('bidang.store'), $data)
            ->assertSessionHasNoErrors();

        $bidang = Bidang::query()->where('nomor_urut', 'HP-UJI-1')->firstOrFail();

        $this->assertSame(StatusBidang::Diserahkan, $bidang->status);

        $this->actingAs($this->operator())
            ->get(route('bidang.show', $bidang))
            ->assertOk()
            ->assertSee('Sudah diserahkan');
    }

    public function test_pilihan_status_pada_form_memuat_seluruh_status(): void
    {
        $halaman = $this->actingAs($this->operator())->get(route('bidang.create'));

        foreach (StatusBidang::cases() as $status) {
            $halaman->assertSee('value="'.$status->value.'"', escape: false)
                ->assertSee($status->label());
        }
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

    public function test_halaman_detail_menandai_tahap_yang_tidak_berlaku(): void
    {
        $kondisional = $this->tahapKondisional();
        $bidang = Bidang::factory()->tanpaTahap($kondisional->kolom)->create();

        $this->actingAs($this->operator())
            ->get(route('bidang.show', $bidang))
            ->assertOk()
            ->assertSee('tidak berlaku untuk bidang ini');
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Instansi $instansi): array
    {
        $data = [
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
            'status' => StatusBidang::Proses->value,
        ];

        foreach (Tahapan::kolomStatus() as $kolom) {
            $data[$kolom] = StatusTahap::Berlaku->value;
        }

        return $data;
    }

    private function operator(): User
    {
        return User::factory()->peran(Peran::Operator)->create();
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
