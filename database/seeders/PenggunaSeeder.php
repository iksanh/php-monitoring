<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Peran;
use App\Models\Instansi;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PenggunaSeeder extends Seeder
{
    public function run(): void
    {
        $cari = fn (string $kode): ?Instansi => Instansi::query()
            ->whereRelation('jenis', 'kode', $kode)
            ->first();

        $pemda = $cari('pemda');
        $kantah = $cari('kantah');
        $kejaksaan = $cari('kejaksaan');

        $daftar = [
            [
                'name' => 'Admin Kantah',
                'email' => 'admin.kantah@example.test',
                'role' => Peran::Admin,
                'instansi_id' => $kantah?->id,
            ],
            [
                'name' => 'Admin Pemda',
                'email' => 'admin.pemda@example.test',
                'role' => Peran::Admin,
                'instansi_id' => $pemda?->id,
            ],
            [
                // Satu-satunya peran yang memutakhirkan data pemantauan.
                'name' => 'Operator Kantah',
                'email' => 'operator@example.test',
                'role' => Peran::Operator,
                'instansi_id' => $kantah?->id,
            ],
            [
                'name' => 'Pemantau Kejaksaan',
                'email' => 'viewer@example.test',
                'role' => Peran::Viewer,
                'instansi_id' => $kejaksaan?->id,
            ],
        ];

        foreach ($daftar as $pengguna) {
            $user = User::query()->firstOrNew(['email' => $pengguna['email']]);

            if ($user->exists) {
                continue;
            }

            // forceFill: `email_verified_at` sengaja tidak fillable pada model.
            $user->forceFill([
                'name' => $pengguna['name'],
                'role' => $pengguna['role'],
                'instansi_id' => $pengguna['instansi_id'],
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ])->save();
        }
    }
}
