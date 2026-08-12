<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Peran;
use App\Models\JenisInstansi;
use App\Models\User;

/**
 * Master jenis instansi: admin saja.
 */
class JenisInstansiPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->berperan(Peran::Admin);
    }

    public function view(User $user, JenisInstansi $jenis): bool
    {
        return $user->berperan(Peran::Admin);
    }

    public function create(User $user): bool
    {
        return $user->berperan(Peran::Admin);
    }

    public function update(User $user, JenisInstansi $jenis): bool
    {
        return $user->berperan(Peran::Admin);
    }

    /**
     * Jenis yang masih dipakai instansi tidak boleh dihapus — nonaktifkan saja
     * bila tidak dipakai lagi untuk instansi baru.
     */
    public function delete(User $user, JenisInstansi $jenis): bool
    {
        return $user->berperan(Peran::Admin) && ! $jenis->terpakai();
    }
}
