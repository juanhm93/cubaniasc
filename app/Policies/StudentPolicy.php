<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PlatformAbility;
use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAbility(PlatformAbility::Students);
    }

    public function view(User $user, Student $student): bool
    {
        return $user->hasAbility(PlatformAbility::Students);
    }

    public function update(User $user, Student $student): bool
    {
        return $user->hasAbility(PlatformAbility::Students);
    }

    public function updateEmail(User $user, Student $student): bool
    {
        return $user->isAdmin() || $user->isOwner();
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->isAdmin() || $user->isOwner();
    }

    public function restore(User $user, Student $student): bool
    {
        return $user->isAdmin() || $user->isOwner();
    }

    public function forceDelete(User $user, Student $student): bool
    {
        return $user->isAdmin() || $user->isOwner();
    }
}
