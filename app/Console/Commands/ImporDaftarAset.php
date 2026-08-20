<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Imports\DaftarAsetPemda;
use App\Models\Bidang;
use App\Models\Instansi;
use App\Support\Impor\HasilAsetPemda;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Memuat daftar aset tanah pemda ke tabel bidang.
 *
 * Dipakai sekali saat memasang data awal, dan sesekali lagi ketika pemda
 * mengirim daftar aset terbaru. Sengaja perintah konsol, bukan halaman impor:
 * berkasnya seribu baris lebih dan berformat aplikasi barang milik daerah,
 * sedangkan halaman impor melayani template aplikasi ini dengan batas baris
 * yang aman untuk permintaan web.
 *
 * Aman diulang. Bidang dikenali dari `nomor_urut` yang dibangun dari kode
 * barang dan nomor register, dan bidang yang sudah ada dilewati — kecuali
 * diminta lain lewat --perbarui.
 *
 * Contoh:
 *   php artisan impor:daftar-aset "docs/Daftar Aset berupa Tanah Pemda THN 2025.xlsx" --pratinjau
 *   php artisan impor:daftar-aset "docs/Daftar Aset berupa Tanah Pemda THN 2025.xlsx"
 */
class ImporDaftarAset extends Command
{
    protected $signature = 'impor:daftar-aset
        {berkas : Path berkas daftar aset (.xlsx)}
        {--instansi= : Nama atau id instansi pemegang hak; wajib bila ada lebih dari satu pemda}
        {--perbarui : Timpa bidang yang nomor urutnya sudah terdaftar}
        {--pratinjau : Tampilkan ringkasan saja, tidak menulis apa pun}';

    protected $description = 'Memuat daftar aset tanah pemda dari berkas Excel menjadi bidang pantauan';

    public function handle(): int
    {
        $instansi = $this->instansi();

        if ($instansi === null) {
            return self::FAILURE;
        }

        try {
            $hasil = (new DaftarAsetPemda)->baca((string) $this->argument('berkas'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->ringkas($hasil, $instansi);

        if ($this->option('pratinjau')) {
            $this->comment('Pratinjau saja — tidak ada data yang ditulis.');

            return self::SUCCESS;
        }

        return $this->tulis($hasil, $instansi);
    }

    /**
     * Instansi pemegang hak. Bila tidak disebut, dipakai satu-satunya instansi
     * berjenis pemda — begitu jumlahnya lebih dari satu, pilihannya harus
     * ditegaskan supaya ribuan bidang tidak menempel ke pemda yang keliru.
     */
    private function instansi(): ?Instansi
    {
        $pilihan = (string) ($this->option('instansi') ?? '');

        if ($pilihan !== '') {
            $instansi = Instansi::query()
                ->when(
                    is_numeric($pilihan),
                    fn ($query) => $query->where('id', (int) $pilihan),
                    fn ($query) => $query->where('nama', $pilihan),
                )
                ->first();

            if ($instansi === null) {
                $this->error('Instansi "'.$pilihan.'" tidak ditemukan.');
            }

            return $instansi;
        }

        $pemda = Instansi::query()
            ->whereHas('jenis', fn ($query) => $query->where('kode', 'pemda'))
            ->orderBy('nama')
            ->get();

        if ($pemda->count() === 1) {
            return $pemda->first();
        }

        $this->error($pemda->isEmpty()
            ? 'Belum ada instansi berjenis pemda. Jalankan ProduksiSeeder atau buat instansinya dulu.'
            : 'Ada '.$pemda->count().' instansi berjenis pemda. Sebutkan yang mana lewat --instansi.');

        foreach ($pemda as $satu) {
            $this->line('  ['.$satu->id.'] '.$satu->nama);
        }

        return null;
    }

    private function ringkas(HasilAsetPemda $hasil, Instansi $instansi): void
    {
        $this->info('Instansi pemegang hak: '.$instansi->nama);

        $baris = [];

        foreach ($hasil->perLembar as $lembar => $jumlah) {
            $baris[] = [$lembar, number_format($jumlah, 0, ',', '.')];
        }

        $baris[] = ['Jumlah terbaca', number_format($hasil->jumlah(), 0, ',', '.')];
        $baris[] = ['— sudah bersertipikat', number_format($hasil->bersertipikat(), 0, ',', '.')];
        $baris[] = ['Baris bukan data, dilewati', number_format($hasil->dilewati, 0, ',', '.')];

        $this->table(['Lembar', 'Baris'], $baris);

        foreach ($hasil->catatan as $catatan) {
            $this->comment($catatan);
        }
    }

    private function tulis(HasilAsetPemda $hasil, Instansi $instansi): int
    {
        $adaSebelumnya = Bidang::withTrashed()
            ->pluck('id', 'nomor_urut')
            ->mapWithKeys(fn (int $id, string $nomor): array => [mb_strtolower($nomor) => $id])
            ->all();

        $perbarui = (bool) $this->option('perbarui');
        $ditambah = 0;
        $diperbarui = 0;
        $dilewati = 0;

        $bar = $this->output->createProgressBar($hasil->jumlah());
        $bar->start();

        try {
            // Satu transaksi untuk seluruh berkas: impor yang putus di tengah
            // meninggalkan daftar bidang separuh jadi, dan tidak ada cara
            // otomatis mengetahui sampai mana ia berhasil.
            DB::transaction(function () use ($hasil, $instansi, $adaSebelumnya, $perbarui, $bar, &$ditambah, &$diperbarui, &$dilewati): void {
                foreach ($hasil->bidang as $atribut) {
                    $atribut['instansi_id'] = $instansi->id;
                    $lama = $adaSebelumnya[mb_strtolower((string) $atribut['nomor_urut'])] ?? null;

                    if ($lama === null) {
                        Bidang::query()->create($atribut);
                        $ditambah++;
                    } elseif ($perbarui) {
                        // Tanggal sertipikat dan serah terima hanya boleh
                        // terisi, tidak pernah dikosongkan: daftar aset pemda
                        // kerap tertinggal dari catatan kantah, dan yang
                        // dicatat operator lebih baru. Penanda diproses juga
                        // dibiarkan apa adanya — memilih bidang yang dikerjakan
                        // itu keputusan kantah, bukan isi berkas pemda.
                        foreach ([Bidang::KOLOM_TERBIT, Bidang::KOLOM_SERAH_TERIMA] as $kolom) {
                            if ($atribut[$kolom] === null) {
                                unset($atribut[$kolom]);
                            }
                        }

                        unset($atribut['tgl_diproses']);

                        Bidang::withTrashed()->findOrFail($lama)->update($atribut);
                        $diperbarui++;
                    } else {
                        $dilewati++;
                    }

                    $bar->advance();
                }
            });
        } catch (Throwable $e) {
            $bar->finish();
            $this->newLine(2);
            $this->error('Impor dibatalkan, tidak ada data yang tersimpan: '.$e->getMessage());

            return self::FAILURE;
        }

        $bar->finish();
        $this->newLine(2);

        $this->info($ditambah.' bidang ditambahkan.');

        if ($diperbarui > 0) {
            $this->info($diperbarui.' bidang diperbarui.');
        }

        if ($dilewati > 0) {
            $this->warn($dilewati.' bidang dilewati karena nomor urutnya sudah terdaftar. Pakai --perbarui bila memang ingin menimpanya.');
        }

        return self::SUCCESS;
    }
}
