<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\SifatTahap;
use App\Models\Bidang;
use App\Support\Tahapan;
use Tests\TestCase;

/**
 * Menjaga kontrak config/tahapan.php: bila pimpinan mengubah nama tahap,
 * test ini tetap hijau; bila strukturnya rusak, test ini yang berteriak.
 */
class TahapanConfigTest extends TestCase
{
    public function test_setiap_tahap_terbaca_lengkap_dan_berurutan(): void
    {
        $tahapan = Tahapan::semua();

        $this->assertNotEmpty($tahapan);

        foreach ($tahapan as $index => $tahap) {
            $this->assertSame($index + 1, $tahap->urutan);
            $this->assertNotSame('', $tahap->kolom);
            $this->assertNotSame('', $tahap->label);
            $this->assertNotSame('', $tahap->unit);
            $this->assertNotSame('', $tahap->dokumen);
        }
    }

    public function test_kolom_tanggal_unik(): void
    {
        $kolom = Tahapan::kolomTanggal();

        $this->assertSame($kolom, array_values(array_unique($kolom)));
    }

    public function test_hanya_tahap_kondisional_yang_punya_kolom_status(): void
    {
        foreach (Tahapan::semua() as $tahap) {
            if ($tahap->sifat === SifatTahap::Kondisional) {
                $this->assertNotNull($tahap->kolomStatus, "Tahap {$tahap->kolom} kondisional tanpa kolom_status.");

                continue;
            }

            $this->assertNull($tahap->kolomStatus, "Tahap {$tahap->kolom} wajib tetapi punya kolom_status.");
        }
    }

    public function test_semua_kolom_tahap_dapat_diisi_massal_pada_model_bidang(): void
    {
        $fillable = (new Bidang)->getFillable();

        foreach (Tahapan::semua() as $tahap) {
            $this->assertContains($tahap->kolom, $fillable);

            if ($tahap->kolomStatus !== null) {
                $this->assertContains($tahap->kolomStatus, $fillable);
            }
        }
    }

    public function test_kolom_penanda_terbit_ada_di_config(): void
    {
        $this->assertNotNull(
            Tahapan::cari(Bidang::KOLOM_TERBIT),
            'Bidang::KOLOM_TERBIT harus menunjuk salah satu kolom pada config/tahapan.php.'
        );
    }
}
