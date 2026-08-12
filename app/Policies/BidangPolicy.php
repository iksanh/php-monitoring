<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Peran;
use App\Models\Bidang;
use App\Models\User;

/**
 * Membaca: semua peran, atas SELURUH data lintas instansi. Keterbukaan lintas
 * instansi adalah tujuan aplikasi ini, jadi tidak ada penyaringan per instansi.
 *
 * Menulis: admin dan operator. Operator adalah satu-satunya peran yang
 * memutakhirkan data pemantauan sehari-hari.
 */
class BidangPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Bidang $bidang): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->berperan(Peran::Admin, Peran::Operator);
    }

    public function update(User $user, Bidang $bidang): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Bidang $bidang): bool
    {
        return $this->create($user);
    }

    public function restore(User $user, Bidang $bidang): bool
    {
        return $user->berperan(Peran::Admin);
    }

    public function forceDelete(User $user, Bidang $bidang): bool
    {
        return $user->berperan(Peran::Admin);
    }
}
