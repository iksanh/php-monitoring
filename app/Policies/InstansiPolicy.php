<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Peran;
use App\Models\Instansi;
use App\Models\User;

/**
 * Master instansi: admin saja.
 */
class InstansiPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->berperan(Peran::Admin);
    }

    public function view(User $user, Instansi $instansi): bool
    {
        return $user->berperan(Peran::Admin);
    }

    public function create(User $user): bool
    {
        return $user->berperan(Peran::Admin);
    }

    public function update(User $user, Instansi $instansi): bool
    {
        return $user->berperan(Peran::Admin);
    }

    /**
     * Instansi yang masih memiliki bidang tidak boleh dihapus — riwayat
     * pemantauan akan kehilangan pemilik asetnya.
     */
    public function delete(User $user, Instansi $instansi): bool
    {
        return $user->berperan(Peran::Admin)
            && ! $instansi->bidang()->exists()
            && ! $instansi->pengguna()->exists();
    }
}
