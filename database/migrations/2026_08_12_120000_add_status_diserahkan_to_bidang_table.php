<?php

use App\Enums\StatusBidang;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menambah nilai `diserahkan` pada kolom status bidang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bidang', function (Blueprint $table) {
            $table->enum('status', array_column(StatusBidang::cases(), 'value'))
                ->default(StatusBidang::Proses->value)
                ->change();
        });
    }

    public function down(): void
    {
        // Nilai baru harus dikembalikan lebih dulu, kalau tidak baris yang
        // memakainya melanggar batasan enum yang menyempit.
        DB::table('bidang')
            ->where('status', StatusBidang::Diserahkan->value)
            ->update(['status' => StatusBidang::Selesai->value]);

        $lama = array_values(array_diff(
            array_column(StatusBidang::cases(), 'value'),
            [StatusBidang::Diserahkan->value],
        ));

        Schema::table('bidang', function (Blueprint $table) use ($lama) {
            $table->enum('status', $lama)
                ->default(StatusBidang::Proses->value)
                ->change();
        });
    }
};
