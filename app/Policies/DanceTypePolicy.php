<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PlatformAbility;
use App\Models\DanceType;
use App\Models\User;

class DanceTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAbility(PlatformAbility::Content);
    }

    public function view(User $user, DanceType $danceType): bool
    {
        return $user->hasAbility(PlatformAbility::Content);
    }

    public function create(User $user): bool
    {
        return $user->hasAbility(PlatformAbility::Content);
    }

    public function update(User $user, DanceType $danceType): bool
    {
        return $user->hasAbility(PlatformAbility::Content);
    }

    public function delete(User $user, DanceType $danceType): bool
    {
        return $user->isAdmin() || $user->isOwner();
    }

    public function restore(User $user, DanceType $danceType): bool
    {
        return $user->isAdmin() || $user->isOwner();
    }

    public function forceDelete(User $user, DanceType $danceType): bool
    {
        return $user->isAdmin() || $user->isOwner();
    }
}
