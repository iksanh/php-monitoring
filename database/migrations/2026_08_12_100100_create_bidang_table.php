<?php

use App\Enums\StatusBidang;
use App\Enums\StatusTahap;
use App\Support\Tahapan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bidang', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_urut')->unique();
            $table->string('nama_aset');
            $table->foreignId('instansi_id')->constrained('instansi');
            $table->string('penggunaan')->nullable();
            $table->string('desa');
            $table->string('kecamatan');
            $table->decimal('luas_m2', 12, 2)->nullable();
            $table->string('nomor_berkas_kkp')->nullable();
            $table->year('tahun_target');
            $table->text('keterangan')->nullable();

            // Kolom tanggal dan kolom status pendamping dibangun dari
            // config('tahapan') supaya tidak ada nama tahap yang dihardcode.
            foreach (Tahapan::semua() as $tahap) {
                $table->date($tahap->kolom)->nullable();
            }

            foreach (Tahapan::semua() as $tahap) {
                if ($tahap->kolomStatus !== null) {
                    $table->enum($tahap->kolomStatus, array_column(StatusTahap::cases(), 'value'))
                        ->default(StatusTahap::Berlaku->value);
                }
            }

            $table->enum('status', array_column(StatusBidang::cases(), 'value'))
                ->default(StatusBidang::Proses->value);

            $table->timestamps();
            $table->softDeletes();

            $table->index('instansi_id');
            $table->index('tahun_target');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bidang');
    }
};
