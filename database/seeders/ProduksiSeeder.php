<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Peran;
use App\Models\Instansi;
use App\Models\JenisInstansi;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Data awal untuk server produksi — SENGAJA tidak dipanggil dari
 * `DatabaseSeeder`, karena seeder itu berisi contoh (bidang, kendala, akun
 * `@example.test`) yang tidak boleh ikut ke server.
 *
 * Isinya hanya yang tidak bisa diinput lewat aplikasi selama belum ada yang
 * bisa login: jenis instansi, instansi nyata, dan satu akun administrator.
 * Data bidang dan kendala diinput manual lewat aplikasi.
 *
 * Aman dijalankan berulang: semua tulis pakai kunci alami (`kode`, `nama`,
 * `email`), dan akun yang sudah ada dilewati — passwordnya tidak pernah
 * ditimpa.
 *
 * Password administrator sengaja TIDAK dibaca dari env: `env()` mengembalikan
 * null begitu `config:cache` dijalankan (dan alur deploy memang menjalankannya),
 * jadi nilainya akan diam-diam terabaikan. Seeder membuat password acak lalu
 * menampilkannya SEKALI di layar; ganti lewat menu profil setelah login.
 *
 * Jalankan di server:
 *   php artisan db:seed --class=Database\\Seeders\\ProduksiSeeder --force
 */
class ProduksiSeeder extends Seeder
{
    private const ADMIN_NAMA = 'iksan';

    private const ADMIN_EMAIL = 'iksanhariji@gmail.com';

    /**
     * @var list<array{kode: string, nama: string}>
     */
    private const JENIS_INSTANSI = [
        ['kode' => 'pemda', 'nama' => 'Pemerintah Daerah'],
        ['kode' => 'kantah', 'nama' => 'Kantor Pertanahan'],
        ['kode' => 'kejaksaan', 'nama' => 'Kejaksaan'],
        ['kode' => 'pemerintah_provinsi', 'nama' => 'Pemerintah Provinsi'],
    ];

    /**
     * @var list<array{nama: string, kode_jenis: string}>
     */
    private const INSTANSI = [
        ['nama' => 'Pemerintah Kabupaten Bone Bolango', 'kode_jenis' => 'pemda'],
        ['nama' => 'Pemerintah Provinsi Gorontalo', 'kode_jenis' => 'pemerintah_provinsi'],
    ];

    public function run(): void
    {
        $this->seedJenisInstansi();
        $this->seedInstansi();
        $this->seedAdmin();
    }

    private function seedJenisInstansi(): void
    {
        foreach (self::JENIS_INSTANSI as $jenis) {
            JenisInstansi::query()->firstOrCreate(
                ['kode' => $jenis['kode']],
                ['nama' => $jenis['nama'], 'aktif' => true],
            );
        }

        $this->lapor('info', 'Jenis instansi: '.count(self::JENIS_INSTANSI).' tersedia.');
    }

    private function seedInstansi(): void
    {
        $jenis = JenisInstansi::query()->pluck('id', 'kode');

        foreach (self::INSTANSI as $instansi) {
            Instansi::query()->firstOrCreate(
                ['nama' => $instansi['nama']],
                ['jenis_instansi_id' => $jenis[$instansi['kode_jenis']], 'aktif' => true],
            );
        }

        $this->lapor('info', 'Instansi: '.count(self::INSTANSI).' tersedia.');
    }

    private function seedAdmin(): void
    {
        if (User::query()->where('email', self::ADMIN_EMAIL)->exists()) {
            $this->lapor('warn', 'Akun '.self::ADMIN_EMAIL.' sudah ada — password dibiarkan apa adanya.');

            return;
        }

        $password = Str::password(16, symbols: false);

        // forceFill: `email_verified_at` sengaja tidak fillable pada model.
        // `password` di-hash otomatis oleh cast `hashed` pada User.
        (new User)->forceFill([
            'name' => self::ADMIN_NAMA,
            'email' => self::ADMIN_EMAIL,
            // Instansi dikosongkan: kolom ini sekadar keterangan asal, bukan
            // pembatas akses. Isi lewat menu Pengguna setelah instansi yang
            // benar dibuat.
            'instansi_id' => null,
            'role' => Peran::Admin,
            'password' => $password,
            'email_verified_at' => now(),
        ])->save();

        $this->lapor('info', 'Administrator dibuat: '.self::ADMIN_EMAIL);
        $this->lapor('warn', "Password sementara: {$password}");
        $this->lapor('warn', 'Catat sekarang — tidak ditampilkan lagi. Segera ganti setelah login.');
    }

    /**
     * `$this->command` baru terisi bila seeder dijalankan lewat `db:seed` —
     * itulah satu-satunya cara pakai yang didukung, karena password sementara
     * hanya muncul di keluaran konsol.
     *
     * @param  'info'|'warn'  $tingkat
     */
    private function lapor(string $tingkat, string $pesan): void
    {
        $this->command->{$tingkat}($pesan);
    }
}
