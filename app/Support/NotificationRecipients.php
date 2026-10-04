<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PlatformAbility;
use App\Enums\RoleSlug;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Who hears about each platform event. Ability-based groups defer to
 * {@see RoleAccess}, so the role → ability map keeps living in one place.
 *
 * Only active users are ever returned: pending accounts have not been approved
 * yet and must not receive internal information.
 */
final class NotificationRecipients
{
    /**
     * Active users holding the ability (owners and admins hold every ability).
     *
     * @return Collection<int, User>
     */
    public static function withAbility(PlatformAbility $ability): Collection
    {
        return self::activeUsers()
            ->get()
            ->filter(fn (User $user): bool => RoleAccess::allows($user, $ability))
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    public static function owners(): Collection
    {
        return self::activeUsers()->where('is_owner', 1)->get();
    }

    /**
     * @return Collection<int, User>
     */
    public static function ownersAndAdmins(): Collection
    {
        return self::activeUsers()
            ->where(function (Builder $query): void {
                $query->where('is_owner', 1)
                    ->orWhereHas('role', fn (Builder $role) => $role->where('slug', RoleSlug::Admin->value));
            })
            ->get();
    }

    /**
     * Active teachers in charge of the given courses.
     *
     * @param  iterable<Course>  $courses
     * @return Collection<int, User>
     */
    public static function teachersOf(iterable $courses): Collection
    {
        $teacherIds = collect($courses)
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values();

        if ($teacherIds->isEmpty()) {
            return new Collection;
        }

        return self::activeUsers()->whereIn('id', $teacherIds)->get();
    }

    /**
     * Merge recipient groups, drop duplicates and leave out whoever triggered
     * the event: nobody needs to be told about what they just did.
     *
     * @param  iterable<User>  ...$groups
     * @return Collection<int, User>
     */
    public static function combine(?User $actor, iterable ...$groups): Collection
    {
        $merged = new Collection;

        foreach ($groups as $group) {
            foreach ($group as $user) {
                $merged->push($user);
            }
        }

        return $merged
            ->unique('id')
            ->reject(fn (User $user): bool => $actor !== null && $user->is($actor))
            ->values();
    }

    /**
     * @return Builder<User>
     */
    private static function activeUsers(): Builder
    {
        return User::query()
            ->with('role')
            ->where('status', 'active');
    }
}
