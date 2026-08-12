<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\KategoriKendala;
use App\Models\Bidang;
use App\Models\Instansi;
use App\Models\Kendala;
use App\Support\Tahapan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Sebaran contoh yang meniru kondisi riil kantor (docs/spec.md bagian 7),
 * supaya dashboard langsung bermakna sesudah `db:seed`.
 *
 * Sebaran ditulis sebagai JUMLAH TAHAP TERISI, bukan nama tahap: nama dan
 * urutan tahap dibaca dari config('tahapan') dan masih mungkin berubah.
 * Bidang dengan n tahap terisi berarti sedang menunggu tahap ke-(n+1).
 */
class BidangSeeder extends Seeder
{
    /**
     * tahap terisi => jumlah bidang. Dengan config saat spec ditulis:
     * 1 = menunggu PKKPR, 2 = menunggu pengukuran, 3 = menunggu peta analisis,
     * 4 = menunggu Panitia A, 7 = siap diserahkan, 8 = sudah diserahkan.
     */
    private const SEBARAN = [
        1 => 2,
        2 => 2,
        3 => 6,
        4 => 7,
        7 => 5,
        8 => 6,
    ];

    /**
     * Sisanya acak, supaya jumlah bidang lewat dari 40.
     */
    private const ACAK = 14;

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

        foreach (self::SEBARAN as $terisi => $jumlah) {
            for ($i = 0; $i < $jumlah; $i++) {
                // Yang mandek di tahap awal sengaja dibuat tua: itulah berkas
                // yang paling perlu terlihat di dashboard.
                $umur = $terisi <= 4 ? [200, 600] : [30, 200];
                $mulai = $this->mulai($terisi, $umur[0], $umur[1]);

                // Sebagian berkas lama ditarget ulang ke tahun berjalan,
                // sebagian tetap tercatat sebagai capaian tahun lampau.
                $tahun = $terisi === $jumlahTahap && $i >= 4 ? $mulai->year : $tahunIni;

                Bidang::factory()
                    ->sampaiTahap($terisi, $mulai)
                    ->tahunTarget($tahun)
                    ->create(['instansi_id' => $this->instansi($instansi)]);
            }
        }

        for ($i = 0; $i < self::ACAK; $i++) {
            // Termasuk beberapa yang berkasnya belum masuk sama sekali.
            $terisi = random_int(0, $jumlahTahap - 1);
            $mulai = $this->mulai(max($terisi, 1), 15, 120);

            Bidang::factory()
                ->sampaiTahap($terisi, $mulai)
                ->tahunTarget($tahunIni)
                ->create(['instansi_id' => $this->instansi($instansi)]);
        }

        $this->kendala();
    }

    /**
     * Kendala aktif dengan kategori bervariasi, plus beberapa yang sudah
     * ditutup supaya riwayatnya tidak kosong.
     *
     * Status bidang tidak disetel di sini — BidangObserver yang menjadikannya
     * `terkendala` begitu kendala aktif tersimpan.
     */
    private function kendala(): void
    {
        $belumSelesai = Bidang::query()
            ->whereNull(Bidang::KOLOM_SERAH_TERIMA)
            ->inRandomOrder()
            ->limit(count(KategoriKendala::cases()) + 4)
            ->get();

        $kategori = KategoriKendala::cases();

        foreach ($belumSelesai as $urutan => $bidang) {
            Kendala::factory()
                ->kategori($kategori[$urutan % count($kategori)])
                ->create([
                    'bidang_id' => $bidang->id,
                    'tanggal_catat' => CarbonImmutable::now()->subDays(random_int(20, 240)),
                ]);
        }

        Bidang::query()
            ->whereDoesntHave('kendala')
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
}
