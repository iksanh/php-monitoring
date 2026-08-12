<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Peran;
use App\Models\Kendala;
use App\Models\User;

/**
 * Kendala mengikuti hak yang sama dengan bidang induknya: semua peran boleh
 * membaca, hanya admin dan operator yang mencatat dan menutup.
 */
class KendalaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Kendala $kendala): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->berperan(Peran::Admin, Peran::Operator);
    }

    public function update(User $user, Kendala $kendala): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Kendala $kendala): bool
    {
        return $this->create($user);
    }
}
