<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Peran;
use App\Models\User;

/**
 * Manajemen pengguna: admin saja.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->berperan(Peran::Admin);
    }

    public function view(User $user, User $target): bool
    {
        return $user->berperan(Peran::Admin);
    }

    public function create(User $user): bool
    {
        return $user->berperan(Peran::Admin);
    }

    public function update(User $user, User $target): bool
    {
        return $user->berperan(Peran::Admin);
    }

    /**
     * Admin tidak boleh menghapus akunnya sendiri, dan admin terakhir tidak
     * boleh dihapus — kalau lolos, aplikasi kehilangan seluruh pengelolanya.
     */
    public function delete(User $user, User $target): bool
    {
        if (! $user->berperan(Peran::Admin) || $user->is($target)) {
            return false;
        }

        if (! $target->berperan(Peran::Admin)) {
            return true;
        }

        return User::query()->where('role', Peran::Admin)->count() > 1;
    }
}
