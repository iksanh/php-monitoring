<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\StatusBidang;
use App\Models\Bidang;
use App\Models\Kendala;
use App\Support\Tahapan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

/**
 * Accessor turunan pada model Bidang.
 *
 * Test ini tidak menyentuh database dan tidak menyebut satu pun nama tahap:
 * semuanya diturunkan dari config('tahapan') supaya tetap hijau ketika
 * pimpinan mengubah nama tahap.
 */
class BidangAccessorTest extends TestCase
{
    public function test_bidang_kosong_belum_punya_tahap_aktif(): void
    {
        $bidang = $this->bidang();

        $this->assertNull($bidang->tahapAktif);
        $this->assertSame(Tahapan::pertama()->kolom, $bidang->tahapBerikut?->kolom);
        $this->assertSame(Tahapan::pertama()->penanggungJawab, $bidang->penanggungJawab);
        $this->assertNull($bidang->umurHari);
        $this->assertSame(0, $bidang->persenProgres);
        $this->assertCount(Tahapan::jumlah(), $bidang->tahapBerlaku());
    }

    public function test_persen_progres_memakai_seluruh_tahap_sebagai_penyebut(): void
    {
        $tahapan = Tahapan::semua();
        $terisi = intdiv(count($tahapan), 2);

        $tanggal = [];

        foreach (array_slice($tahapan, 0, $terisi) as $tahap) {
            $tanggal[$tahap->kolom] = '2026-03-0'.min($tahap->urutan, 9);
        }

        $this->assertSame(
            (int) round($terisi / count($tahapan) * 100),
            $this->bidang($tanggal)->persenProgres
        );
    }

    public function test_bidang_tuntas(): void
    {
        $bidang = $this->bidang($this->seluruhTanggal());
        $terakhir = Tahapan::semua()[Tahapan::jumlah() - 1];

        $this->assertSame($terakhir->kolom, $bidang->tahapAktif?->kolom);
        $this->assertNull($bidang->tahapBerikut);
        $this->assertNull($bidang->penanggungJawab);
        $this->assertSame(100, $bidang->persenProgres);
        $this->assertSame(Bidang::KONDISI_TUNTAS, $bidang->kondisiTahap);
    }

    public function test_tahap_aktif_diambil_dari_tanggal_terisi_terjauh_walau_ada_tahap_dilewati(): void
    {
        $tahapan = Tahapan::semua();

        // Operator mengisi tahap ke-1 dan ke-3, tahap ke-2 dilewati.
        $bidang = $this->bidang([
            $tahapan[0]->kolom => '2026-01-05',
            $tahapan[2]->kolom => '2026-03-05',
        ]);

        $this->assertSame($tahapan[2]->kolom, $bidang->tahapAktif?->kolom);
        $this->assertSame($tahapan[3]->kolom, $bidang->tahapBerikut?->kolom);
        $this->assertSame($tahapan[3]->penanggungJawab, $bidang->penanggungJawab);
    }

    /**
     * Kondisi berjalan disebut dengan label_menunggu tahap yang ditunggu,
     * bukan label tahap yang sudah selesai.
     */
    public function test_kondisi_tahap_memakai_label_menunggu_tahap_berikut(): void
    {
        $tahapan = Tahapan::semua();

        $bidang = $this->bidang([$tahapan[0]->kolom => '2026-01-05']);

        $this->assertSame($tahapan[1]->labelMenunggu, $bidang->kondisiTahap);
    }

    public function test_umur_hari_dihitung_sampai_sertipikat_terbit(): void
    {
        $bidang = $this->bidang([
            Tahapan::pertama()->kolom => '2026-01-01',
            Bidang::KOLOM_TERBIT => '2026-03-02',
        ]);

        $this->assertSame(60, $bidang->umurHari);
    }

    public function test_umur_hari_dihitung_sampai_hari_ini_bila_sertipikat_belum_terbit(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-12 09:30:00'));

        $bidang = $this->bidang([Tahapan::pertama()->kolom => '2026-07-13']);

        $this->assertSame(30, $bidang->umurHari);
    }

    public function test_accessor_ikut_berubah_saat_tanggal_diperbarui(): void
    {
        $tahapan = Tahapan::semua();

        $bidang = $this->bidang([$tahapan[0]->kolom => '2026-01-05']);
        $this->assertSame($tahapan[0]->kolom, $bidang->tahapAktif?->kolom);

        $bidang->setAttribute($tahapan[1]->kolom, '2026-02-05');

        $this->assertSame($tahapan[1]->kolom, $bidang->tahapAktif?->kolom);
    }

    public function test_status_hitung_proses_saat_sertipikat_belum_terbit(): void
    {
        $bidang = $this->bidang([Tahapan::pertama()->kolom => '2026-01-05']);

        $this->assertSame(StatusBidang::Proses, $bidang->statusHitung);
    }

    public function test_status_hitung_selesai_saat_sertipikat_terbit(): void
    {
        $bidang = $this->bidang([
            Tahapan::pertama()->kolom => '2026-01-05',
            Bidang::KOLOM_TERBIT => '2026-05-05',
        ]);

        $this->assertSame(StatusBidang::Selesai, $bidang->statusHitung);
    }

    public function test_status_hitung_diserahkan_saat_serah_terima_terisi(): void
    {
        $bidang = $this->bidang($this->seluruhTanggal());

        $this->assertSame(StatusBidang::Diserahkan, $bidang->statusHitung);
    }

    /**
     * Kendala aktif menang atas seluruh syarat lain, termasuk atas bidang yang
     * sudah diserahkan.
     */
    public function test_status_hitung_terkendala_menang_atas_yang_lain(): void
    {
        $bidang = $this->bidang($this->seluruhTanggal());

        $bidang->setRelation('kendalaAktif', new Collection([new Kendala]));

        $this->assertSame(StatusBidang::Terkendala, $bidang->statusHitung);
    }

    /**
     * @param  array<string, mixed>  $atribut
     */
    private function bidang(array $atribut = []): Bidang
    {
        return (new Bidang)->forceFill($atribut);
    }

    /**
     * Seluruh tahap terisi, berjarak wajar.
     *
     * @return array<string, string>
     */
    private function seluruhTanggal(): array
    {
        $tanggal = [];
        $mulai = CarbonImmutable::parse('2025-01-10');

        foreach (Tahapan::semua() as $tahap) {
            $tanggal[$tahap->kolom] = $mulai->addDays($tahap->urutan * 20)->toDateString();
        }

        return $tanggal;
    }
}
