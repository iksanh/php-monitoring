<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatusBidang;
use App\Enums\StatusTahap;
use App\Models\Bidang;
use App\Models\Instansi;
use App\Support\Tahap;
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
            'status' => StatusBidang::Proses,
        ];

        // Semua tahap mulai kosong dan berlaku; state di bawah yang mengisinya.
        foreach (Tahapan::semua() as $tahap) {
            $atribut[$tahap->kolom] = null;

            if ($tahap->kolomStatus !== null) {
                $atribut[$tahap->kolomStatus] = StatusTahap::Berlaku;
            }
        }

        return $atribut;
    }

    /**
     * Isi tanggal untuk sejumlah tahap berlaku pertama, berjarak realistis.
     *
     * Tahap yang dinyatakan tidak berlaku otomatis dilewati, jadi state ini
     * harus dipasang SESUDAH state tanpaTahap().
     */
    public function sampaiTahap(int $jumlah, ?CarbonInterface $mulai = null): static
    {
        return $this->state(function (array $attributes) use ($jumlah, $mulai): array {
            $tanggal = $mulai !== null
                ? CarbonImmutable::instance($mulai)
                : $this->awalTahun($attributes);

            $nilai = [];

            foreach (array_slice($this->tahapBerlaku($attributes), 0, max($jumlah, 0)) as $tahap) {
                $tanggal = $tanggal->addDays(fake()->numberBetween(7, 45));
                $nilai[$tahap->kolom] = $tanggal;
            }

            return $nilai;
        });
    }

    /**
     * Seluruh tahap berlaku terisi dan bidang ditandai selesai.
     */
    public function tuntas(?CarbonInterface $mulai = null): static
    {
        return $this->sampaiTahap(Tahapan::jumlah(), $mulai)
            ->state(fn (): array => ['status' => StatusBidang::Selesai]);
    }

    /**
     * Nyatakan satu tahap kondisional tidak berlaku untuk bidang ini.
     *
     * @param  string  $kolom  kolom tanggal tahapnya, mis. dari config('tahapan')
     */
    public function tanpaTahap(string $kolom): static
    {
        $tahap = Tahapan::cari($kolom);

        if ($tahap === null || $tahap->kolomStatus === null) {
            throw new \InvalidArgumentException(
                "Tahap [{$kolom}] tidak ada di config('tahapan') atau bukan tahap kondisional."
            );
        }

        return $this->state(fn (): array => [
            $tahap->kolomStatus => StatusTahap::TidakBerlaku,
            $tahap->kolom => null,
        ]);
    }

    public function status(StatusBidang $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }

    public function tahunTarget(int $tahun): static
    {
        return $this->state(fn (): array => ['tahun_target' => $tahun]);
    }

    /**
     * Tahap berlaku menurut atribut yang sudah terbentuk sejauh ini.
     *
     * @param  array<string, mixed>  $attributes
     * @return list<Tahap>
     */
    private function tahapBerlaku(array $attributes): array
    {
        return array_values(array_filter(
            Tahapan::semua(),
            function (Tahap $tahap) use ($attributes): bool {
                if ($tahap->kolomStatus === null) {
                    return true;
                }

                $status = $attributes[$tahap->kolomStatus] ?? StatusTahap::Berlaku;

                if ($status instanceof StatusTahap) {
                    return $status->berlaku();
                }

                return $status !== StatusTahap::TidakBerlaku->value;
            },
        ));
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
