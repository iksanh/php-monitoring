<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\JenisInstansi;
use Illuminate\Database\Seeder;

/**
 * Jenis bawaan. Admin bebas menambah jenis lain lewat menu Jenis Instansi.
 */
class JenisInstansiSeeder extends Seeder
{
    /**
     * @var list<array{kode: string, nama: string}>
     */
    public const BAWAAN = [
        ['kode' => 'pemda', 'nama' => 'Pemerintah Daerah'],
        ['kode' => 'kantah', 'nama' => 'Kantor Pertanahan'],
        ['kode' => 'kejaksaan', 'nama' => 'Kejaksaan'],
    ];

    public function run(): void
    {
        foreach (self::BAWAAN as $jenis) {
            JenisInstansi::query()->firstOrCreate(
                ['kode' => $jenis['kode']],
                ['nama' => $jenis['nama'], 'aktif' => true],
            );
        }
    }
}
