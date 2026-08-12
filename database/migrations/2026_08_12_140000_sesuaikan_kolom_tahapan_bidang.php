<?php

use App\Enums\KategoriKendala;
use App\Enums\StatusBidang;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Penyesuaian skema ke daftar tahapan baru (docs/spec.md bagian 2 dan 3):
 *
 *  - tahap PKKPR, Peta Analisis, dan Panitia A masuk
 *  - Pengumuman 30 Hari dan Pemenuhan Kewajiban dihapus, berikut kolom status
 *    tahap kondisionalnya — seluruh tahap kini wajib
 *  - Pemeriksaan Tanah berganti nama menjadi Pemeriksaan Panitia A, ISINYA
 *    DISALIN ke kolom baru sebelum kolom lama dihapus
 *  - kendala mendapat kolom kategori
 *  - status bidang dihitung ulang, sebab nilainya kini turunan
 *
 * Tiap langkah dijaga `hasColumn`. Sebabnya: migrasi
 * `2026_08_12_100100_create_bidang_table` membangun kolom tanggal dari
 * config('tahapan') saat dijalankan, jadi pada database baru kolom tahap sudah
 * langsung berbentuk baru dan migrasi ini tidak perlu berbuat apa-apa.
 * Pada database yang sudah berisi data, semua langkah di bawah berjalan.
 */
return new class extends Migration
{
    /**
     * Kolom tanggal tahap baru. Sengaja ditulis sebagai konstanta, bukan
     * dibaca dari config: isi config akan berubah lagi di kemudian hari,
     * sedangkan migrasi harus tetap berarti sama seperti saat ditulis.
     */
    private const TAMBAH = [
        'tgl_pkkpr' => 'tgl_permohonan',
        'tgl_peta_analisis' => 'tgl_pengukuran',
        'tgl_panitia_a' => 'tgl_peta_analisis',
    ];

    private const HAPUS = [
        'tgl_pengumuman',
        'tgl_pemeriksaan',
        'tgl_kewajiban',
        'pengumuman_status',
        'kewajiban_status',
    ];

    public function up(): void
    {
        Schema::table('bidang', function (Blueprint $table) {
            foreach (self::TAMBAH as $kolom => $sesudah) {
                if (! Schema::hasColumn('bidang', $kolom)) {
                    $table->date($kolom)->nullable()->after($sesudah);
                }
            }
        });

        // Salin dulu, hapus belakangan: Pemeriksaan Tanah dan Pemeriksaan
        // Panitia A adalah tahap yang sama dengan nama baru.
        if (Schema::hasColumn('bidang', 'tgl_pemeriksaan')) {
            DB::table('bidang')
                ->whereNull('tgl_panitia_a')
                ->whereNotNull('tgl_pemeriksaan')
                ->update(['tgl_panitia_a' => DB::raw('tgl_pemeriksaan')]);
        }

        $dibuang = array_values(array_filter(
            self::HAPUS,
            fn (string $kolom): bool => Schema::hasColumn('bidang', $kolom),
        ));

        if ($dibuang !== []) {
            Schema::table('bidang', function (Blueprint $table) use ($dibuang) {
                $table->dropColumn($dibuang);
            });
        }

        if (! Schema::hasColumn('kendala', 'kategori')) {
            Schema::table('kendala', function (Blueprint $table) {
                $table->enum('kategori', array_column(KategoriKendala::cases(), 'value'))
                    ->default(KategoriKendala::BerkasKurang->value)
                    ->after('bidang_id');
            });
        }

        $this->hitungUlangStatus();
    }

    public function down(): void
    {
        Schema::table('bidang', function (Blueprint $table) {
            foreach (['tgl_pengumuman' => 'tgl_pengukuran', 'tgl_pemeriksaan' => 'tgl_pengumuman'] as $kolom => $sesudah) {
                if (! Schema::hasColumn('bidang', $kolom)) {
                    $table->date($kolom)->nullable()->after($sesudah);
                }
            }

            if (! Schema::hasColumn('bidang', 'tgl_kewajiban')) {
                $table->date('tgl_kewajiban')->nullable()->after('tgl_sk');
            }

            foreach (['pengumuman_status', 'kewajiban_status'] as $kolom) {
                if (! Schema::hasColumn('bidang', $kolom)) {
                    $table->enum($kolom, ['berlaku', 'tidak_berlaku'])->default('berlaku');
                }
            }
        });

        // Isi tahap Panitia A dikembalikan ke kolom lamanya. Isi tahap
        // Pengumuman dan Pemenuhan Kewajiban tidak dapat dipulihkan — kedua
        // tahap itu dihapus dari spec, datanya hilang saat up().
        if (Schema::hasColumn('bidang', 'tgl_panitia_a')) {
            DB::table('bidang')
                ->whereNull('tgl_pemeriksaan')
                ->whereNotNull('tgl_panitia_a')
                ->update(['tgl_pemeriksaan' => DB::raw('tgl_panitia_a')]);
        }

        $dibuang = array_values(array_filter(
            array_keys(self::TAMBAH),
            fn (string $kolom): bool => Schema::hasColumn('bidang', $kolom),
        ));

        if ($dibuang !== []) {
            Schema::table('bidang', function (Blueprint $table) use ($dibuang) {
                $table->dropColumn($dibuang);
            });
        }

        if (Schema::hasColumn('kendala', 'kategori')) {
            Schema::table('kendala', function (Blueprint $table) {
                $table->dropColumn('kategori');
            });
        }
    }

    /**
     * Status bidang kini turunan tanggal tahap dan kendala aktif, bukan lagi
     * isian operator. Baris lama diselaraskan dengan aturan itu sekali di sini;
     * selanjutnya dijaga oleh App\Observers\BidangObserver.
     *
     * Urutan update mengikuti tabel di docs/spec.md bagian 3, dari syarat
     * paling longgar ke paling menang: `terkendala` ditulis terakhir.
     */
    private function hitungUlangStatus(): void
    {
        DB::table('bidang')->update(['status' => StatusBidang::Proses->value]);

        DB::table('bidang')
            ->whereNotNull('tgl_sertipikat')
            ->whereNull('tgl_serah_terima')
            ->update(['status' => StatusBidang::Selesai->value]);

        DB::table('bidang')
            ->whereNotNull('tgl_serah_terima')
            ->update(['status' => StatusBidang::Diserahkan->value]);

        DB::table('bidang')
            ->whereExists(fn ($query) => $query
                ->select(DB::raw(1))
                ->from('kendala')
                ->whereColumn('kendala.bidang_id', 'bidang.id')
                ->whereNull('kendala.tanggal_selesai'))
            ->update(['status' => StatusBidang::Terkendala->value]);
    }
};
