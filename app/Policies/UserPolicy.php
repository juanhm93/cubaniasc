<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PlatformAbility;
use App\Enums\RoleSlug;
use App\Models\Role;
use App\Models\User;

/**
 * Who may change another user's role or status from /admin/users.
 *
 * - Nobody changes the owner, and nobody changes themselves.
 * - Only the owner grants or removes the admin role, or touches another admin;
 *   otherwise a compromised admin account could create more admins or lock
 *   the other admins out.
 */
class UserPolicy
{
    public function updateRole(User $actor, User $target, Role $newRole): bool
    {
        if (! $this->canManage($actor, $target)) {
            return false;
        }

        return $actor->isOwner() || $newRole->slug !== RoleSlug::Admin->value;
    }

    public function updateStatus(User $actor, User $target): bool
    {
        return $this->canManage($actor, $target);
    }

    private function canManage(User $actor, User $target): bool
    {
        if (! $actor->hasAbility(PlatformAbility::AdminUsers) || $target->isOwner() || $actor->is($target)) {
            return false;
        }

        return $actor->isOwner() || ! $target->isAdmin();
    }
}
