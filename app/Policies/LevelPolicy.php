<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PlatformAbility;
use App\Models\Level;
use App\Models\User;

class LevelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAbility(PlatformAbility::Content);
    }

    public function view(User $user, Level $level): bool
    {
        return $user->hasAbility(PlatformAbility::Content);
    }

    public function create(User $user): bool
    {
        return $user->hasAbility(PlatformAbility::Content);
    }

    public function update(User $user, Level $level): bool
    {
        return $user->hasAbility(PlatformAbility::Content);
    }

    public function delete(User $user, Level $level): bool
    {
        return $user->isAdmin() || $user->isOwner();
    }

    public function restore(User $user, Level $level): bool
    {
        return $user->isAdmin() || $user->isOwner();
    }

    public function forceDelete(User $user, Level $level): bool
    {
        return $user->isAdmin() || $user->isOwner();
    }
}
