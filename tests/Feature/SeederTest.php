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
use App\Support\Tahapan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menjamin `db:seed` menghasilkan papan yang langsung bermakna: bukan sekadar
 * baris terisi, tetapi sebaran yang membuat setiap grafik dashboard punya isi.
 */
class SeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_seeder_aman_dijalankan_dua_kali(): void
    {
        $sebelum = [Instansi::query()->count(), User::query()->count(), Bidang::query()->count()];

        $this->seed();

        $this->assertSame(
            $sebelum,
            [Instansi::query()->count(), User::query()->count(), Bidang::query()->count()],
            'Menjalankan db:seed dua kali tidak boleh menggandakan atau menggagalkan apa pun.'
        );
    }

    public function test_instansi_dan_pengguna_terbentuk(): void
    {
        $this->assertSame(3, Instansi::query()->count());

        $this->assertSame(2, User::query()->where('role', Peran::Admin)->count());
        $this->assertSame(1, User::query()->where('role', Peran::Operator)->count());
        $this->assertSame(1, User::query()->where('role', Peran::Viewer)->count());
    }

    public function test_bidang_cukup_banyak_dan_statusnya_beragam(): void
    {
        $this->assertGreaterThanOrEqual(40, Bidang::query()->count());

        foreach (StatusBidang::cases() as $status) {
            $this->assertGreaterThan(
                0,
                Bidang::query()->where('status', $status)->count(),
                "Tidak ada bidang berstatus {$status->value}."
            );
        }
    }

    public function test_setiap_tahap_punya_bidang_yang_sudah_melewatinya(): void
    {
        foreach (Tahapan::semua() as $tahap) {
            $this->assertGreaterThan(
                0,
                Bidang::query()->whereNotNull($tahap->kolom)->count(),
                "Tidak ada bidang yang melewati tahap {$tahap->label}."
            );
        }
    }

    public function test_ada_bidang_dengan_tahap_kondisional_tidak_berlaku(): void
    {
        foreach (Tahapan::kolomStatus() as $kolom) {
            $this->assertGreaterThan(
                0,
                Bidang::query()->where($kolom, StatusTahap::TidakBerlaku)->count(),
                "Tidak ada bidang dengan {$kolom} = tidak_berlaku."
            );
        }
    }

    public function test_ada_bidang_belum_mulai_dan_bidang_tuntas(): void
    {
        $this->assertGreaterThan(
            0,
            Bidang::query()->whereNull(Tahapan::pertama()->kolom)->count()
        );

        $this->assertGreaterThan(
            0,
            Bidang::query()->whereNotNull(Bidang::KOLOM_TERBIT)->count()
        );
    }

    public function test_ada_kendala_aktif_dan_kendala_yang_sudah_ditutup(): void
    {
        $this->assertGreaterThan(0, Kendala::query()->whereNull('tanggal_selesai')->count());
        $this->assertGreaterThan(0, Kendala::query()->whereNotNull('tanggal_selesai')->count());
    }

    public function test_penanggung_jawab_tertahan_terbagi_ke_dua_pihak(): void
    {
        $pihak = Bidang::query()
            ->with('instansi')
            ->get()
            ->map(fn (Bidang $bidang) => $bidang->penanggungJawab?->value)
            ->filter()
            ->unique();

        // Grafik donat dashboard kehilangan maknanya bila hanya satu pihak.
        $this->assertGreaterThan(1, $pihak->count());
    }
}
