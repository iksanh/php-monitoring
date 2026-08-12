<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\StatusBidang;
use App\Models\Bidang;
use App\Models\Instansi;
use App\Models\Kendala;
use App\Support\Tahap;
use App\Support\Tahapan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Sebaran contoh yang dibuat supaya dashboard langsung bermakna sesudah
 * `db:seed`: ada yang tuntas, ada yang mandek lama di tahap berbeda, ada yang
 * tahap kondisionalnya tidak berlaku, ada yang berkendala aktif.
 */
class BidangSeeder extends Seeder
{
    public function run(): void
    {
        /** @var Collection<int, Instansi> $instansi */
        $instansi = Instansi::query()->get();

        if ($instansi->isEmpty()) {
            throw new RuntimeException('Jalankan InstansiSeeder lebih dulu.');
        }

        // `db:seed` harus aman diulang: tanpa penjaga ini, pemanggilan kedua
        // menabrak keunikan `nomor_urut`.
        if (Bidang::query()->withTrashed()->exists()) {
            $this->command->warn(
                'Tabel bidang sudah berisi data — BidangSeeder dilewati. '
                .'Kosongkan tabel bidang lebih dulu bila ingin sebaran contoh yang baru.'
            );

            return;
        }

        $jumlahTahap = Tahapan::jumlah();
        $tahunIni = (int) date('Y');

        // 12 bidang tuntas sampai serah terima. Sebagian ditargetkan tahun
        // berjalan supaya progress bar capaian tahun ini ada isinya, sisanya
        // capaian tahun-tahun lalu.
        for ($i = 0; $i < 12; $i++) {
            $mulai = $this->mulai($jumlahTahap, 60, 420);

            Bidang::factory()
                ->tuntas($mulai)
                ->status(StatusBidang::Diserahkan)
                ->tahunTarget($i < 7 ? $tahunIni : $mulai->year)
                ->create(['instansi_id' => $this->instansi($instansi)]);
        }

        // 5 bidang yang sertipikatnya sudah terbit tetapi asetnya belum
        // diserahkan — inilah beda "selesai" dan "sudah diserahkan".
        for ($i = 0; $i < 5; $i++) {
            $mulai = $this->mulai($jumlahTahap - 1, 30, 180);

            Bidang::factory()
                ->sampaiTahap($jumlahTahap - 1, $mulai)
                ->status(StatusBidang::Selesai)
                ->tahunTarget($tahunIni)
                ->create(['instansi_id' => $this->instansi($instansi)]);
        }

        // 10 bidang mandek lama di tahap yang berbeda-beda. Separuhnya berkas
        // tahun lalu yang ditarget ulang ke tahun berjalan.
        for ($i = 0; $i < 10; $i++) {
            $tahap = ($i % 5) + 1;
            $mulai = $this->mulai($tahap, 400, 800);

            Bidang::factory()
                ->sampaiTahap($tahap, $mulai)
                ->tahunTarget($i % 2 === 0 ? $tahunIni : $mulai->year)
                ->create(['instansi_id' => $this->instansi($instansi)]);
        }

        // 14 bidang berjalan normal dengan target tahun ini.
        for ($i = 0; $i < 14; $i++) {
            $tahap = random_int(1, $jumlahTahap - 1);
            $mulai = $this->mulai($tahap, 15, 90);

            Bidang::factory()
                ->sampaiTahap($tahap, $mulai)
                ->tahunTarget($tahunIni)
                ->create(['instansi_id' => $this->instansi($instansi)]);
        }

        // 5 bidang target tahun ini yang berkasnya belum dimasukkan sama sekali.
        for ($i = 0; $i < 5; $i++) {
            Bidang::factory()
                ->tahunTarget($tahunIni)
                ->create(['instansi_id' => $this->instansi($instansi)]);
        }

        // 6 bidang dengan tahap kondisional yang tidak berlaku.
        foreach ($this->tahapKondisional() as $tahap) {
            for ($i = 0; $i < 3; $i++) {
                $sampai = $i === 0 ? $jumlahTahap : random_int(2, $jumlahTahap - 2);
                $mulai = $this->mulai($sampai, 30, 200);

                $factory = Bidang::factory()
                    ->tanpaTahap($tahap->kolom)
                    ->sampaiTahap($sampai, $mulai)
                    ->tahunTarget($i === 2 ? $mulai->year : $tahunIni);

                if ($i === 0) {
                    // Seluruh tahap berlaku terisi, termasuk serah terima.
                    $factory = $factory->status(StatusBidang::Diserahkan);
                }

                $factory->create(['instansi_id' => $this->instansi($instansi)]);
            }
        }

        // 6 bidang berkendala aktif — inilah yang mengisi kartu "terkendala".
        for ($i = 0; $i < 6; $i++) {
            $tahap = random_int(1, 5);
            $mulai = $this->mulai($tahap, 200, 500);

            $bidang = Bidang::factory()
                ->sampaiTahap($tahap, $mulai)
                ->tahunTarget($tahunIni)
                ->status(StatusBidang::Terkendala)
                ->create(['instansi_id' => $this->instansi($instansi)]);

            Kendala::factory()->create([
                'bidang_id' => $bidang->id,
                'tanggal_catat' => $mulai->addDays(random_int(30, 120)),
            ]);
        }

        // Beberapa kendala yang sudah ditutup, supaya riwayat tidak kosong.
        Bidang::query()
            ->where('status', StatusBidang::Proses)
            ->inRandomOrder()
            ->limit(5)
            ->get()
            ->each(function (Bidang $bidang): void {
                Kendala::factory()->selesai()->create(['bidang_id' => $bidang->id]);
            });
    }

    /**
     * Tanggal mulai yang membuat sejumlah tahap muat sebelum hari ini.
     */
    private function mulai(int $jumlahTahap, int $minTambahan, int $maxTambahan): CarbonImmutable
    {
        return CarbonImmutable::now()
            ->subDays($jumlahTahap * 30 + random_int($minTambahan, $maxTambahan))
            ->startOfDay();
    }

    /**
     * @param  Collection<int, Instansi>  $instansi
     */
    private function instansi(Collection $instansi): int
    {
        // Aset paling banyak milik Pemda, sebagian kecil milik instansi lain.
        $urutan = $instansi->values();
        $pilihan = random_int(1, 100) <= 60
            ? $urutan->first()
            : $urutan->random();

        return (int) $pilihan?->id;
    }

    /**
     * @return list<Tahap>
     */
    private function tahapKondisional(): array
    {
        return array_values(array_filter(
            Tahapan::semua(),
            fn (Tahap $tahap): bool => $tahap->kondisional(),
        ));
    }
}
