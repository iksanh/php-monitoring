<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jenis instansi dipindah dari kolom enum ke tabel master supaya admin bisa
 * menambah dan mengubahnya sendiri.
 *
 * `kode` sengaja dipisah dari `nama`: kode dipakai kode aplikasi (seeder,
 * pencarian instansi bawaan) dan tidak berubah, sedangkan nama bebas diubah
 * admin tanpa merusak apa pun.
 */
return new class extends Migration
{
    /**
     * @var list<array{kode: string, nama: string}>
     */
    private const BAWAAN = [
        ['kode' => 'pemda', 'nama' => 'Pemerintah Daerah'],
        ['kode' => 'kantah', 'nama' => 'Kantor Pertanahan'],
        ['kode' => 'kejaksaan', 'nama' => 'Kejaksaan'],
    ];

    public function up(): void
    {
        Schema::create('jenis_instansi', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        foreach (self::BAWAAN as $baris) {
            DB::table('jenis_instansi')->insert($baris + [
                'aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('instansi', function (Blueprint $table) {
            $table->foreignId('jenis_instansi_id')->nullable()->after('nama')->constrained('jenis_instansi');
        });

        // Pindahkan nilai enum lama ke relasi baru.
        foreach (DB::table('jenis_instansi')->get() as $jenis) {
            DB::table('instansi')
                ->where('jenis', $jenis->kode)
                ->update(['jenis_instansi_id' => $jenis->id]);
        }

        // Jaring pengaman bila ada nilai enum di luar tiga bawaan.
        $pertama = DB::table('jenis_instansi')->orderBy('id')->value('id');
        DB::table('instansi')->whereNull('jenis_instansi_id')->update(['jenis_instansi_id' => $pertama]);

        Schema::table('instansi', function (Blueprint $table) {
            $table->dropColumn('jenis');
        });
    }

    public function down(): void
    {
        Schema::table('instansi', function (Blueprint $table) {
            $table->string('jenis')->nullable()->after('nama');
        });

        foreach (DB::table('jenis_instansi')->get() as $jenis) {
            DB::table('instansi')
                ->where('jenis_instansi_id', $jenis->id)
                ->update(['jenis' => $jenis->kode]);
        }

        // Jenis buatan admin tidak punya padanan di enum lama.
        $kodeBawaan = array_column(self::BAWAAN, 'kode');
        DB::table('instansi')->whereNotIn('jenis', $kodeBawaan)->update(['jenis' => $kodeBawaan[0]]);

        Schema::table('instansi', function (Blueprint $table) {
            $table->dropConstrainedForeignId('jenis_instansi_id');
        });

        Schema::table('instansi', function (Blueprint $table) {
            $table->enum('jenis', array_column(self::BAWAAN, 'kode'))->nullable(false)->change();
        });

        Schema::dropIfExists('jenis_instansi');
    }
};
