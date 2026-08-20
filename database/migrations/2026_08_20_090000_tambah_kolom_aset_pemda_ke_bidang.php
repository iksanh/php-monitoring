<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom tambahan yang dituntut daftar aset tanah pemda yang sesungguhnya.
 *
 * Hanya yang berguna bagi pertanahan yang diambil. Kolom akuntansi milik
 * daftar BMD — harga perolehan dan nilai buku — sengaja tidak ikut: aplikasi
 * ini memantau sertipikasi, bukan menilai aset.
 *
 * `kecamatan` dilonggarkan jadi nullable karena daftar aset hanya mencatat
 * satu kolom letak, dan hanya sebagian kecil barisnya menyebut kecamatan.
 * Memaksa kolom ini terisi berarti mengarang data yang tidak ada di sumbernya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bidang', function (Blueprint $table) {
            // Perangkat daerah pemakai aset. Pemegang haknya tetap badan hukum
            // pemda pada `instansi_id`; ini sekadar penunjuk siapa yang
            // memakai dan dihubungi saat berkas kurang.
            $table->string('pengguna_barang')->nullable()->after('instansi_id');

            $table->string('jenis_hak', 50)->nullable()->after('luas_m2');

            // Dasar penguasaan sebelum sertipikat terbit: SPPHT, surat hibah,
            // SKST, dan sejenisnya. Inilah berkas yang diperiksa di loket.
            $table->string('alas_hak')->nullable()->after('jenis_hak');
            $table->date('tgl_alas_hak')->nullable()->after('alas_hak');
            $table->string('asal_usul', 50)->nullable()->after('tgl_alas_hak');

            $table->string('nomor_sertipikat')->nullable()->after('nomor_berkas_kkp');

            $table->index('pengguna_barang');
        });

        Schema::table('bidang', function (Blueprint $table) {
            $table->string('kecamatan')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('bidang', function (Blueprint $table) {
            $table->dropIndex(['pengguna_barang']);
            $table->dropColumn([
                'pengguna_barang',
                'jenis_hak',
                'alas_hak',
                'tgl_alas_hak',
                'asal_usul',
                'nomor_sertipikat',
            ]);
        });

        // Baris yang kecamatannya kosong diisi penanda lebih dahulu, supaya
        // kolom bisa dikembalikan menjadi wajib tanpa menggagalkan migrasi.
        DB::table('bidang')->whereNull('kecamatan')->update(['kecamatan' => '-']);

        Schema::table('bidang', function (Blueprint $table) {
            $table->string('kecamatan')->nullable(false)->change();
        });
    }
};
