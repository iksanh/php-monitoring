<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KategoriKendala;
use App\Enums\PenanggungJawab;
use App\Enums\Peran;
use App\Enums\StatusBidang;
use App\Models\Bidang;
use App\Models\Instansi;
use App\Models\Kendala;
use App\Models\User;
use App\Support\Filter\FilterBidang;
use App\Support\Tahapan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DaftarBidangTest extends TestCase
{
    use RefreshDatabase;

    public function test_pencarian_menjaring_nama_aset_dan_nomor_urut(): void
    {
        Bidang::factory()->create(['nomor_urut' => 'HP-100', 'nama_aset' => 'Puskesmas Wanasari']);
        Bidang::factory()->create(['nomor_urut' => 'HP-200', 'nama_aset' => 'Kantor Camat']);

        $this->masuk()->get(route('bidang.index', ['cari' => 'Wanasari']))
            ->assertOk()
            ->assertSee('HP-100')
            ->assertDontSee('HP-200');

        $this->masuk()->get(route('bidang.index', ['cari' => 'HP-200']))
            ->assertOk()
            ->assertSee('Kantor Camat')
            ->assertDontSee('HP-100');
    }

    public function test_penyaringan_status_sudah_diserahkan(): void
    {
        // Status turunan, jadi dibentuk lewat tanggalnya: yang satu sampai
        // serah terima, yang lain berhenti di penerbitan sertipikat.
        Bidang::factory()->tuntas()->create(['nomor_urut' => 'HP-SERAH']);
        Bidang::factory()->sampaiTahap(Tahapan::jumlah() - 1)->create(['nomor_urut' => 'HP-SELESAI']);

        $this->masuk()->get(route('bidang.index', ['status' => StatusBidang::Diserahkan->value]))
            ->assertOk()
            ->assertSee('HP-SERAH')
            ->assertDontSee('HP-SELESAI');
    }

    public function test_penyaringan_instansi_status_dan_tahun(): void
    {
        $instansi = Instansi::factory()->create();

        $cocok = Bidang::factory()->create([
            'nomor_urut' => 'HP-COCOK',
            'instansi_id' => $instansi->id,
            'tahun_target' => 2026,
        ]);
        Kendala::factory()->create(['bidang_id' => $cocok->id]);

        Bidang::factory()->create(['nomor_urut' => 'HP-LAIN', 'tahun_target' => 2024]);

        $this->masuk()->get(route('bidang.index', ['instansi' => $instansi->id]))
            ->assertSee('HP-COCOK')->assertDontSee('HP-LAIN');

        $this->masuk()->get(route('bidang.index', ['status' => StatusBidang::Terkendala->value]))
            ->assertSee('HP-COCOK')->assertDontSee('HP-LAIN');

        $this->masuk()->get(route('bidang.index', ['tahun' => 2024]))
            ->assertSee('HP-LAIN')->assertDontSee('HP-COCOK');
    }

    /**
     * Tahap aktif tidak punya kolom sendiri, jadi penyaringnya bekerja di SQL.
     * Hasilnya harus sama dengan accessor.
     */
    public function test_penyaringan_tahap_aktif_sama_dengan_accessor(): void
    {
        $this->seed();
        $tahapan = Tahapan::semua();

        foreach ($tahapan as $tahap) {
            $harapan = Bidang::query()->get()
                ->filter(fn (Bidang $bidang): bool => $bidang->tahapAktif?->kolom === $tahap->kolom)
                ->count();

            $hasil = (new FilterBidang(tahapAktif: $tahap->kolom))
                ->terapkan(Bidang::query())
                ->count();

            $this->assertSame($harapan, $hasil, "Penyaringan tahap aktif {$tahap->label} tidak cocok dengan accessor.");
        }
    }

    public function test_penyaringan_belum_mulai(): void
    {
        $this->seed();

        $harapan = Bidang::query()->get()
            ->filter(fn (Bidang $bidang): bool => $bidang->tahapAktif === null)
            ->count();

        $hasil = (new FilterBidang(tahapAktif: FilterBidang::BELUM_MULAI))
            ->terapkan(Bidang::query())
            ->count();

        $this->assertGreaterThan(0, $harapan);
        $this->assertSame($harapan, $hasil);
    }

    public function test_penyaringan_penanggung_jawab_sama_dengan_accessor(): void
    {
        $this->seed();

        foreach (PenanggungJawab::cases() as $pihak) {
            $harapan = Bidang::query()->get()
                ->filter(fn (Bidang $bidang): bool => $bidang->penanggungJawab === $pihak)
                ->count();

            $hasil = (new FilterBidang(penanggungJawab: $pihak))
                ->terapkan(Bidang::query())
                ->count();

            $this->assertGreaterThan(0, $harapan, "Data contoh tidak punya bidang tertahan di {$pihak->value}.");
            $this->assertSame($harapan, $hasil, "Penyaringan penanggung jawab {$pihak->value} tidak cocok dengan accessor.");
        }
    }

    /**
     * Yang disaring adalah kendala yang masih terbuka; kendala yang sudah
     * ditutup tersimpan sebagai riwayat dan tidak lagi menjaring bidangnya.
     */
    public function test_penyaringan_kategori_kendala(): void
    {
        $sengketa = Bidang::factory()->create(['nomor_urut' => 'HP-SENGKETA']);
        Kendala::factory()->kategori(KategoriKendala::Sengketa)->create(['bidang_id' => $sengketa->id]);

        $hutan = Bidang::factory()->create(['nomor_urut' => 'HP-HUTAN']);
        Kendala::factory()->kategori(KategoriKendala::KawasanHutan)->create(['bidang_id' => $hutan->id]);

        $tutup = Bidang::factory()->create(['nomor_urut' => 'HP-TUTUP']);
        Kendala::factory()->kategori(KategoriKendala::Sengketa)->selesai()->create(['bidang_id' => $tutup->id]);

        $this->masuk()->get(route('bidang.index', ['kendala' => KategoriKendala::Sengketa->value]))
            ->assertOk()
            ->assertSee('HP-SENGKETA')
            ->assertDontSee('HP-HUTAN')
            ->assertDontSee('HP-TUTUP');
    }

    public function test_daftar_menampilkan_kondisi_berjalan_dan_kategori_kendala(): void
    {
        $tahapan = Tahapan::semua();

        $bidang = Bidang::factory()->sampaiTahap(1)->create(['nomor_urut' => 'HP-KONDISI']);
        Kendala::factory()->kategori(KategoriKendala::BerkasKurang)->create(['bidang_id' => $bidang->id]);

        $this->masuk()->get(route('bidang.index'))
            ->assertOk()
            ->assertSee($tahapan[1]->labelMenunggu)
            ->assertSee(mb_strtolower(KategoriKendala::BerkasKurang->label()));
    }

    public function test_filter_tersimpan_di_query_string_pagination(): void
    {
        Bidang::factory()->count(30)->create();

        $halaman = $this->masuk()->get(route('bidang.index', [
            'status' => StatusBidang::Proses->value,
            'cari' => 'a',
        ]));

        $halaman->assertOk()
            ->assertSee('status='.StatusBidang::Proses->value, escape: false)
            ->assertSee('cari=a', escape: false);
    }

    public function test_pagination_dua_puluh_lima_per_halaman(): void
    {
        Bidang::factory()->count(30)->create();

        $this->masuk()->get(route('bidang.index'))
            ->assertOk()
            ->assertViewHas('daftar', fn ($daftar): bool => $daftar->perPage() === 25 && $daftar->count() === 25);
    }

    public function test_daftar_tidak_menimbulkan_query_beruntun(): void
    {
        Bidang::factory()->count(20)->create();

        $this->masuk();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get(route('bidang.index'))->assertOk();

        $queryInstansi = collect(DB::getQueryLog())
            ->filter(fn (array $baris): bool => str_contains((string) $baris['query'], 'from "instansi"'))
            ->count();

        // Satu query eager load instansi untuk seluruh baris, plus satu query
        // daftar instansi pada kotak penyaring.
        $this->assertLessThanOrEqual(2, $queryInstansi, 'Terjadi N+1 pada relasi instansi.');
    }

    public function test_penyaringan_tidak_sah_diabaikan_bukan_error(): void
    {
        Bidang::factory()->create(['nomor_urut' => 'HP-ADA']);

        $this->masuk()->get(route('bidang.index', [
            'tahap' => 'kolom_yang_tidak_ada',
            'status' => 'entah',
            'penanggung_jawab' => 'siapa',
            'instansi' => 'bukan-angka',
        ]))->assertOk()->assertSee('HP-ADA');
    }

    private function masuk(): self
    {
        return $this->actingAs(User::factory()->peran(Peran::Viewer)->create());
    }
}
