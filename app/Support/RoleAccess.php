<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PlatformAbility;
use App\Enums\RoleSlug;
use App\Models\User;

final class RoleAccess
{
    /**
     * @return list<PlatformAbility>
     */
    public static function abilitiesFor(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        if ($user->isOwner() || $user->isAdmin()) {
            return PlatformAbility::cases();
        }

        return match ($user->role?->slug) {
            RoleSlug::Teacher->value => [
                PlatformAbility::Content,
                PlatformAbility::Courses,
                PlatformAbility::OneTimeSessions,
            ],
            RoleSlug::AdminStaff->value => [
                PlatformAbility::Payments,
                PlatformAbility::Courses,
                PlatformAbility::OneTimeSessions,
                PlatformAbility::Students,
            ],
            RoleSlug::Staff->value => [
                PlatformAbility::Courses,
                PlatformAbility::OneTimeSessions,
            ],
            default => [],
        };
    }

    /**
     * @return array{
     *     content: bool,
     *     payments: bool,
     *     courses: bool,
     *     oneTimeSessions: bool,
     *     students: bool,
     *     adminUsers: bool
     * }
     */
    public static function mapFor(?User $user): array
    {
        $granted = self::abilitiesFor($user);
        $map = [];

        foreach (PlatformAbility::cases() as $ability) {
            $map[$ability->value] = in_array($ability, $granted, true);
        }

        return $map;
    }

    public static function allows(?User $user, PlatformAbility $ability): bool
    {
        return in_array($ability, self::abilitiesFor($user), true);
    }
}
