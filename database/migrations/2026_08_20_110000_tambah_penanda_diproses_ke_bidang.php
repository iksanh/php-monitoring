<?php

use App\Enums\StatusBidang;
use App\Models\Bidang;
use App\Support\Tahapan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Memisahkan daftar aset dari bidang yang benar-benar dikerjakan.
 *
 * Daftar aset pemda memuat ribuan bidang; Kantor Pertanahan memilih sebagian
 * untuk diproses tahun berjalan. Tanpa pemisahan ini seluruh aset yang belum
 * bersertipikat terbaca "dalam proses" — angka yang menyesatkan pimpinan.
 *
 * `tgl_diproses` terisi berarti bidang sedang dipantau. Kosong berarti masih
 * antrean di daftar aset.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bidang', function (Blueprint $table) {
            $table->date('tgl_diproses')->nullable()->after('tahun_target');
            $table->index('tgl_diproses');

            $table->enum('status', array_column(StatusBidang::cases(), 'value'))
                ->default(StatusBidang::Proses->value)
                ->change();
        });

        // Aset yang sertipikatnya sudah terbit sudah berada di tangan pemda —
        // begitu tercatat di daftar aset mereka, serah terimanya sudah lewat.
        // Tanggalnya disamakan dengan terbitnya sertipikat karena daftar aset
        // tidak menyimpan tanggal serah terima tersendiri.
        DB::table('bidang')
            ->whereNotNull(Bidang::KOLOM_TERBIT)
            ->whereNull(Bidang::KOLOM_SERAH_TERIMA)
            ->update([Bidang::KOLOM_SERAH_TERIMA => DB::raw(Bidang::KOLOM_TERBIT)]);

        // Bidang yang tanggal tahapnya sudah tercatat tetapi belum diserahkan
        // memang sedang dikerjakan, jadi ditandai dipantau. Yang sudah
        // diserahkan dilewati: itu riwayat aset lama, bukan pekerjaan berjalan.
        DB::table('bidang')
            ->whereNull(Bidang::KOLOM_SERAH_TERIMA)
            ->where(function ($query) {
                foreach (Tahapan::kolomTanggal() as $kolom) {
                    $query->orWhereNotNull($kolom);
                }
            })
            ->update(['tgl_diproses' => now()->toDateString()]);

        $this->hitungUlangStatus();
    }

    public function down(): void
    {
        DB::table('bidang')
            ->where('status', StatusBidang::BelumDiproses->value)
            ->update(['status' => StatusBidang::Proses->value]);

        $lama = array_values(array_diff(
            array_column(StatusBidang::cases(), 'value'),
            [StatusBidang::BelumDiproses->value],
        ));

        Schema::table('bidang', function (Blueprint $table) use ($lama) {
            $table->enum('status', $lama)
                ->default(StatusBidang::Proses->value)
                ->change();
        });

        Schema::table('bidang', function (Blueprint $table) {
            $table->dropIndex(['tgl_diproses']);
            $table->dropColumn('tgl_diproses');
        });

        // Serah terima tiruan yang dipasang up() dilepas kembali supaya data
        // tidak mengaku sudah diserahkan padahal tidak pernah dicatat begitu.
        DB::table('bidang')
            ->whereNotNull(Bidang::KOLOM_TERBIT)
            ->whereColumn(Bidang::KOLOM_SERAH_TERIMA, Bidang::KOLOM_TERBIT)
            ->update([Bidang::KOLOM_SERAH_TERIMA => null]);
    }

    /**
     * Kolom `status` hanya ditulis BidangObserver, dan observer itu baru jalan
     * saat model disimpan. Baris yang sudah ada disentuh sekali di sini supaya
     * statusnya mengikuti aturan yang baru.
     */
    private function hitungUlangStatus(): void
    {
        Bidang::withTrashed()->with('kendalaAktif')->chunkById(500, function ($daftar): void {
            foreach ($daftar as $bidang) {
                $bidang->save();
            }
        });
    }
};
