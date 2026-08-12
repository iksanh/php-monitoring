<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Sengaja TANPA trait WithoutModelEvents: status bidang ditulis
     * App\Observers\BidangObserver, jadi data contoh akan berstatus `proses`
     * semua bila event model dimatikan selama seeding.
     */
    public function run(): void
    {
        $this->call([
            JenisInstansiSeeder::class,
            InstansiSeeder::class,
            PenggunaSeeder::class,
            BidangSeeder::class,
        ]);
    }
}
