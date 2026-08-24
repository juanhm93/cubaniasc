<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DanceType;
use App\Models\User;

class DanceTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, DanceType $danceType): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, DanceType $danceType): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, DanceType $danceType): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, DanceType $danceType): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, DanceType $danceType): bool
    {
        return $user->isAdmin();
    }
}
