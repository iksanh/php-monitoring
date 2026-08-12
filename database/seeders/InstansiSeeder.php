<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Instansi;
use App\Models\JenisInstansi;
use Illuminate\Database\Seeder;

class InstansiSeeder extends Seeder
{
    public function run(): void
    {
        $daftar = [
            ['nama' => 'Pemerintah Kabupaten Sukamaju', 'kode_jenis' => 'pemda'],
            ['nama' => 'Kantor Pertanahan Kabupaten Sukamaju', 'kode_jenis' => 'kantah'],
            ['nama' => 'Kejaksaan Negeri Sukamaju', 'kode_jenis' => 'kejaksaan'],
        ];

        $jenis = JenisInstansi::query()->pluck('id', 'kode');

        foreach ($daftar as $instansi) {
            Instansi::query()->firstOrCreate(
                ['nama' => $instansi['nama']],
                ['jenis_instansi_id' => $jenis[$instansi['kode_jenis']], 'aktif' => true],
            );
        }
    }
}
