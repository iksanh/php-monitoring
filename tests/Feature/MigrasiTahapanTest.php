<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KategoriKendala;
use App\Enums\StatusBidang;
use App\Models\Instansi;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Migrasi penyesuaian kolom tahapan diuji pada skema LAMA.
 *
 * Di database kosong migrasi ini tidak berbuat apa-apa: migrasi pembuat tabel
 * membaca config('tahapan') saat dijalankan, jadi kolomnya sudah berbentuk
 * baru. Yang perlu dijamin justru jalur satunya — database kantor yang sudah
 * berisi data — maka skema lama dibangun ulang di sini lebih dahulu.
 */
class MigrasiTahapanTest extends TestCase
{
    use RefreshDatabase;

    private const KOLOM_LAMA = [
        'tgl_pengumuman',
        'tgl_pemeriksaan',
        'tgl_kewajiban',
    ];

    private const KOLOM_BARU = [
        'tgl_pkkpr',
        'tgl_peta_analisis',
        'tgl_panitia_a',
    ];

    public function test_isi_tgl_pemeriksaan_pindah_ke_tgl_panitia_a(): void
    {
        $id = $this->bidangLama(['tgl_pemeriksaan' => '2026-03-15']);

        $this->migrasi()->up();

        $baris = DB::table('bidang')->where('id', $id)->first();

        $this->assertNotNull($baris);
        $this->assertStringStartsWith('2026-03-15', (string) $baris->tgl_panitia_a);
    }

    public function test_kolom_tahap_lama_dihapus_dan_kolom_baru_dibuat(): void
    {
        $this->bidangLama();

        $this->migrasi()->up();

        foreach ([...self::KOLOM_LAMA, 'pengumuman_status', 'kewajiban_status'] as $kolom) {
            $this->assertFalse(Schema::hasColumn('bidang', $kolom), "Kolom [{$kolom}] seharusnya sudah dihapus.");
        }

        foreach (self::KOLOM_BARU as $kolom) {
            $this->assertTrue(Schema::hasColumn('bidang', $kolom), "Kolom [{$kolom}] seharusnya sudah ada.");
        }
    }

    public function test_kendala_mendapat_kategori_dengan_nilai_bawaan(): void
    {
        $id = $this->bidangLama();
        $this->kendalaLama($id);

        $this->migrasi()->up();

        $this->assertTrue(Schema::hasColumn('kendala', 'kategori'));

        $this->assertSame(
            KategoriKendala::BerkasKurang->value,
            DB::table('kendala')->where('bidang_id', $id)->value('kategori')
        );
    }

    public function test_status_dihitung_ulang_dari_tanggal_dan_kendala(): void
    {
        $proses = $this->bidangLama(['status' => StatusBidang::Diserahkan->value]);
        $selesai = $this->bidangLama(['tgl_sertipikat' => '2026-05-05', 'status' => StatusBidang::Proses->value]);
        $diserahkan = $this->bidangLama([
            'tgl_sertipikat' => '2026-05-05',
            'tgl_serah_terima' => '2026-06-05',
            'status' => StatusBidang::Proses->value,
        ]);

        // Kendala terbuka menang atas seluruh syarat lain.
        $terkendala = $this->bidangLama(['tgl_serah_terima' => '2026-06-05']);
        $this->kendalaLama($terkendala);

        $this->migrasi()->up();

        $this->assertSame(StatusBidang::Proses->value, $this->statusBidang($proses));
        $this->assertSame(StatusBidang::Selesai->value, $this->statusBidang($selesai));
        $this->assertSame(StatusBidang::Diserahkan->value, $this->statusBidang($diserahkan));
        $this->assertSame(StatusBidang::Terkendala->value, $this->statusBidang($terkendala));
    }

    public function test_down_mengembalikan_kolom_lama_beserta_isi_panitia_a(): void
    {
        $id = $this->bidangLama(['tgl_pemeriksaan' => '2026-03-15']);

        $migrasi = $this->migrasi();
        $migrasi->up();
        $migrasi->down();

        foreach ([...self::KOLOM_LAMA, 'pengumuman_status', 'kewajiban_status'] as $kolom) {
            $this->assertTrue(Schema::hasColumn('bidang', $kolom), "Kolom [{$kolom}] seharusnya dipulihkan.");
        }

        foreach (self::KOLOM_BARU as $kolom) {
            $this->assertFalse(Schema::hasColumn('bidang', $kolom), "Kolom [{$kolom}] seharusnya dihapus lagi.");
        }

        $this->assertFalse(Schema::hasColumn('kendala', 'kategori'));

        $this->assertStringStartsWith(
            '2026-03-15',
            (string) DB::table('bidang')->where('id', $id)->value('tgl_pemeriksaan')
        );
    }

    /**
     * Pada database baru seluruh langkah harus terlewati tanpa galat, sebab
     * kolomnya sudah berbentuk baru sejak migrasi pembuat tabel.
     */
    public function test_aman_dijalankan_pada_skema_yang_sudah_baru(): void
    {
        $migrasi = $this->migrasi();
        $migrasi->up();
        $migrasi->up();

        foreach (self::KOLOM_BARU as $kolom) {
            $this->assertTrue(Schema::hasColumn('bidang', $kolom));
        }

        $this->assertTrue(Schema::hasColumn('kendala', 'kategori'));
    }

    private function migrasi(): Migration
    {
        return require database_path('migrations/2026_08_12_140000_sesuaikan_kolom_tahapan_bidang.php');
    }

    /**
     * Kembalikan tabel `bidang` dan `kendala` ke bentuk sebelum penyesuaian,
     * lalu isi satu baris.
     *
     * @param  array<string, mixed>  $atribut
     */
    private function bidangLama(array $atribut = []): int
    {
        $this->skemaLama();

        static $urut = 0;

        return (int) DB::table('bidang')->insertGetId(array_merge([
            'nomor_urut' => 'HP-LAMA-'.(++$urut),
            'nama_aset' => 'Kantor Lama',
            'instansi_id' => Instansi::factory()->create()->id,
            'desa' => 'Sukamaju',
            'kecamatan' => 'Kota Utara',
            'tahun_target' => 2026,
            'status' => StatusBidang::Proses->value,
            'created_at' => now(),
            'updated_at' => now(),
        ], $atribut));
    }

    private function kendalaLama(int $bidangId): void
    {
        DB::table('kendala')->insert([
            'bidang_id' => $bidangId,
            'uraian' => 'Berkas belum lengkap.',
            'tanggal_catat' => '2026-04-01',
            'tanggal_selesai' => null,
            'dicatat_oleh' => 'Operator',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function skemaLama(): void
    {
        if (Schema::hasColumn('bidang', 'tgl_pemeriksaan')) {
            return;
        }

        Schema::table('bidang', function (Blueprint $table) {
            foreach (self::KOLOM_LAMA as $kolom) {
                $table->date($kolom)->nullable();
            }

            foreach (['pengumuman_status', 'kewajiban_status'] as $kolom) {
                $table->enum($kolom, ['berlaku', 'tidak_berlaku'])->default('berlaku');
            }

            $table->dropColumn(self::KOLOM_BARU);
        });

        if (Schema::hasColumn('kendala', 'kategori')) {
            Schema::table('kendala', function (Blueprint $table) {
                $table->dropColumn('kategori');
            });
        }
    }

    private function statusBidang(int $id): string
    {
        return (string) DB::table('bidang')->where('id', $id)->value('status');
    }
}
