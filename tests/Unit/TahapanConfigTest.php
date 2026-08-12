<?php

declare(strict_types=1);

namespace Tests\Unit;

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
            $this->assertNotSame('', $tahap->labelMenunggu);
            $this->assertNotSame('', $tahap->unit);
            $this->assertNotSame('', $tahap->dokumen);
        }
    }

    public function test_kolom_tanggal_unik(): void
    {
        $kolom = Tahapan::kolomTanggal();

        $this->assertSame($kolom, array_values(array_unique($kolom)));
    }

    /**
     * Label menunggu dipakai sebagai label sumbu grafik dan kolom daftar
     * bidang; dua tahap dengan label sama membuat keduanya tak terbedakan.
     */
    public function test_label_menunggu_unik(): void
    {
        $label = array_map(fn ($tahap): string => $tahap->labelMenunggu, Tahapan::semua());

        $this->assertSame($label, array_values(array_unique($label)));
    }

    public function test_semua_kolom_tahap_dapat_diisi_massal_pada_model_bidang(): void
    {
        $fillable = (new Bidang)->getFillable();

        foreach (Tahapan::semua() as $tahap) {
            $this->assertContains($tahap->kolom, $fillable);
        }
    }

    /**
     * Status adalah nilai turunan yang ditulis BidangObserver — bukan isian
     * yang boleh dikirim dari form.
     */
    public function test_status_tidak_dapat_diisi_massal(): void
    {
        $this->assertNotContains('status', (new Bidang)->getFillable());
    }

    public function test_kolom_penanda_terbit_dan_serah_terima_ada_di_config(): void
    {
        $this->assertNotNull(
            Tahapan::cari(Bidang::KOLOM_TERBIT),
            'Bidang::KOLOM_TERBIT harus menunjuk salah satu kolom pada config/tahapan.php.'
        );

        $this->assertNotNull(
            Tahapan::cari(Bidang::KOLOM_SERAH_TERIMA),
            'Bidang::KOLOM_SERAH_TERIMA harus menunjuk salah satu kolom pada config/tahapan.php.'
        );
    }
}
