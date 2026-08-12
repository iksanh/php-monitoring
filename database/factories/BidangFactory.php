<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Bidang;
use App\Models\Instansi;
use App\Support\Tahapan;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bidang>
 */
class BidangFactory extends Factory
{
    protected $model = Bidang::class;

    /**
     * Penomoran berjalan supaya `nomor_urut` tetap unik lintas pemanggilan.
     */
    private static int $urut = 0;

    /**
     * Status sengaja tidak diisi di sini: nilainya turunan tanggal tahap dan
     * kendala aktif, ditulis BidangObserver saat model disimpan.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $penggunaan = fake()->randomElement([
            'Kantor', 'Sekolah', 'Puskesmas', 'Pasar', 'Lapangan', 'Gudang', 'Rumah Dinas',
        ]);

        $desa = fake()->randomElement([
            'Sukamaju', 'Cibeureum', 'Wanasari', 'Karangsari', 'Mekarjaya',
            'Sidorejo', 'Tanjungsari', 'Purwodadi', 'Banyuurip', 'Girimulyo',
        ]);

        $tahunTarget = (int) date('Y');

        $atribut = [
            'nomor_urut' => sprintf('HP-%d-%04d', $tahunTarget, ++self::$urut),
            'nama_aset' => $penggunaan.' '.$desa,
            'instansi_id' => Instansi::factory(),
            'penggunaan' => $penggunaan,
            'desa' => $desa,
            'kecamatan' => fake()->randomElement([
                'Kota Utara', 'Kota Selatan', 'Sukaraja', 'Cileungsi', 'Kedungwuni',
            ]),
            'luas_m2' => fake()->randomFloat(2, 250, 25000),
            'nomor_berkas_kkp' => fake()->boolean(80)
                ? sprintf('%d/HP/%d', fake()->numberBetween(100, 9999), $tahunTarget)
                : null,
            'tahun_target' => $tahunTarget,
            'keterangan' => null,
        ];

        // Semua tahap mulai kosong; state di bawah yang mengisinya.
        foreach (Tahapan::kolomTanggal() as $kolom) {
            $atribut[$kolom] = null;
        }

        return $atribut;
    }

    /**
     * Isi tanggal untuk sejumlah tahap pertama, berjarak realistis.
     */
    public function sampaiTahap(int $jumlah, ?CarbonInterface $mulai = null): static
    {
        return $this->state(function (array $attributes) use ($jumlah, $mulai): array {
            $tanggal = $mulai !== null
                ? CarbonImmutable::instance($mulai)
                : $this->awalTahun($attributes);

            $nilai = [];

            foreach (array_slice(Tahapan::semua(), 0, max($jumlah, 0)) as $tahap) {
                $tanggal = $tanggal->addDays(fake()->numberBetween(7, 45));
                $nilai[$tahap->kolom] = $tanggal;
            }

            return $nilai;
        });
    }

    /**
     * Seluruh tahap terisi sampai serah terima.
     */
    public function tuntas(?CarbonInterface $mulai = null): static
    {
        return $this->sampaiTahap(Tahapan::jumlah(), $mulai);
    }

    public function tahunTarget(int $tahun): static
    {
        return $this->state(fn (): array => ['tahun_target' => $tahun]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function awalTahun(array $attributes): CarbonImmutable
    {
        $tahun = $attributes['tahun_target'] ?? date('Y');

        return CarbonImmutable::create((int) $tahun, 1, 1)
            ->addDays(fake()->numberBetween(0, 60));
    }
}
