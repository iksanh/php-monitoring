<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\StatusTahap;
use App\Models\Bidang;
use App\Support\Tahap;
use App\Support\Tahapan;
use Carbon\CarbonImmutable;
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

    public function test_tahap_kondisional_tidak_berlaku_dikeluarkan_dari_perhitungan(): void
    {
        $kondisional = $this->tahapKondisional();
        $kolomStatus = $kondisional->kolomStatus;
        $this->assertNotNull($kolomStatus);

        $bidang = $this->bidang([$kolomStatus => StatusTahap::TidakBerlaku->value]);

        $kolomBerlaku = array_map(
            fn (Tahap $tahap): string => $tahap->kolom,
            $bidang->tahapBerlaku()
        );

        $this->assertNotContains($kondisional->kolom, $kolomBerlaku);
        $this->assertCount(Tahapan::jumlah() - 1, $bidang->tahapBerlaku());
    }

    public function test_tahap_tidak_berlaku_dilewati_saat_menentukan_tahap_berikut(): void
    {
        $kondisional = $this->tahapKondisional();
        $kolomStatus = $kondisional->kolomStatus;
        $this->assertNotNull($kolomStatus);

        // Isi seluruh tahap sebelum tahap kondisional itu.
        $tanggal = [];
        $mulai = CarbonImmutable::parse('2026-01-05');

        foreach (Tahapan::semua() as $tahap) {
            if ($tahap->urutan >= $kondisional->urutan) {
                break;
            }

            $tanggal[$tahap->kolom] = $mulai->addDays($tahap->urutan * 10)->toDateString();
        }

        $bidang = $this->bidang($tanggal + [$kolomStatus => StatusTahap::TidakBerlaku->value]);

        $sesudahnya = $this->tahapSetelah($kondisional);

        $this->assertSame($sesudahnya?->kolom, $bidang->tahapBerikut?->kolom);
        $this->assertSame($sesudahnya?->penanggungJawab, $bidang->penanggungJawab);
    }

    public function test_persen_progres_memakai_penyebut_tahap_berlaku_saja(): void
    {
        $kondisional = $this->tahapKondisional();
        $kolomStatus = $kondisional->kolomStatus;
        $this->assertNotNull($kolomStatus);

        $bidang = $this->bidang([$kolomStatus => StatusTahap::TidakBerlaku->value]);

        // Isi separuh (dibulatkan ke bawah) dari tahap yang berlaku.
        $berlaku = $bidang->tahapBerlaku();
        $terisi = intdiv(count($berlaku), 2);

        foreach (array_slice($berlaku, 0, $terisi) as $tahap) {
            $bidang->setAttribute($tahap->kolom, '2026-03-0'.min($tahap->urutan, 9));
        }

        $this->assertSame(
            (int) round($terisi / count($berlaku) * 100),
            $bidang->persenProgres
        );
    }

    public function test_tanggal_pada_tahap_tidak_berlaku_tidak_ikut_dihitung(): void
    {
        $kondisional = $this->tahapKondisional();
        $kolomStatus = $kondisional->kolomStatus;
        $this->assertNotNull($kolomStatus);

        // Operator terlanjur mengisi tanggal, lalu tahapnya ditandai tidak berlaku.
        $bidang = $this->bidang([
            $kolomStatus => StatusTahap::TidakBerlaku->value,
            $kondisional->kolom => '2026-02-10',
        ]);

        $this->assertNull($bidang->tahapAktif);
        $this->assertSame(0, $bidang->persenProgres);
    }

    public function test_bidang_tuntas(): void
    {
        $tanggal = [];
        $mulai = CarbonImmutable::parse('2025-01-10');

        foreach (Tahapan::semua() as $tahap) {
            $tanggal[$tahap->kolom] = $mulai->addDays($tahap->urutan * 20)->toDateString();
        }

        $bidang = $this->bidang($tanggal);
        $terakhir = Tahapan::semua()[Tahapan::jumlah() - 1];

        $this->assertSame($terakhir->kolom, $bidang->tahapAktif?->kolom);
        $this->assertNull($bidang->tahapBerikut);
        $this->assertNull($bidang->penanggungJawab);
        $this->assertSame(100, $bidang->persenProgres);
    }

    public function test_tahap_aktif_diambil_dari_tanggal_terisi_terjauh_walau_ada_tahap_dilewati(): void
    {
        $berlaku = $this->bidang()->tahapBerlaku();

        // Operator mengisi tahap ke-1 dan ke-3, tahap ke-2 dilewati.
        $bidang = $this->bidang([
            $berlaku[0]->kolom => '2026-01-05',
            $berlaku[2]->kolom => '2026-03-05',
        ]);

        $this->assertSame($berlaku[2]->kolom, $bidang->tahapAktif?->kolom);
        $this->assertSame($berlaku[3]->kolom, $bidang->tahapBerikut?->kolom);
        $this->assertSame($berlaku[3]->penanggungJawab, $bidang->penanggungJawab);
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
        $berlaku = $this->bidang()->tahapBerlaku();

        $bidang = $this->bidang([$berlaku[0]->kolom => '2026-01-05']);
        $this->assertSame($berlaku[0]->kolom, $bidang->tahapAktif?->kolom);

        $bidang->setAttribute($berlaku[1]->kolom, '2026-02-05');

        $this->assertSame($berlaku[1]->kolom, $bidang->tahapAktif?->kolom);
    }

    /**
     * @param  array<string, mixed>  $atribut
     */
    private function bidang(array $atribut = []): Bidang
    {
        return (new Bidang)->forceFill($atribut);
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

    private function tahapSetelah(Tahap $tahap): ?Tahap
    {
        return Tahapan::semua()[$tahap->urutan] ?? null;
    }
}
