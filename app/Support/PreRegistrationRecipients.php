<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\RoleSlug;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class PreRegistrationRecipients
{
    /**
     * Active staff that must be alerted when a new pre-registration arrives:
     * the owner plus the admin, teacher and admin staff roles.
     *
     * @return Collection<int, User>
     */
    public static function staff(): Collection
    {
        return User::query()
            ->where('status', 'active')
            ->where(function (Builder $query): void {
                $query->where('is_owner', 1)
                    ->orWhereHas('role', fn (Builder $role) => $role->whereIn('slug', [
                        RoleSlug::Admin->value,
                        RoleSlug::Teacher->value,
                        RoleSlug::AdminStaff->value,
                    ]));
            })
            ->get();
    }
}
