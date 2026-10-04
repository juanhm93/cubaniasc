<?php

namespace Tests\Feature;

use App\Enums\PlatformAbility;
use App\Models\Course;
use App\Models\Role;
use App\Models\User;
use App\Support\NotificationRecipients;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationRecipientsTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $slug, array $attributes = []): User
    {
        $role = Role::query()->where('slug', $slug)->first() ?? Role::factory()->create(['slug' => $slug]);

        return User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
            ...$attributes,
        ]);
    }

    /**
     * @param  iterable<User>  $users
     * @return list<int>
     */
    private function ids(iterable $users): array
    {
        $ids = collect($users)->pluck('id')->sort()->values()->all();

        return $ids;
    }

    public function test_with_ability_follows_the_role_access_map(): void
    {
        $owner = $this->userWithRole('staff', ['is_owner' => 1]);
        $admin = $this->userWithRole('admin');
        $adminStaff = $this->userWithRole('admin_staff');
        $this->userWithRole('teacher');
        $this->userWithRole('staff');

        $this->assertSame(
            $this->ids([$owner, $admin, $adminStaff]),
            $this->ids(NotificationRecipients::withAbility(PlatformAbility::Payments)),
        );
    }

    public function test_pending_users_are_never_recipients(): void
    {
        $this->userWithRole('admin', ['status' => 'pending']);
        $this->userWithRole('staff', ['is_owner' => 1, 'status' => 'pending']);

        $this->assertCount(0, NotificationRecipients::withAbility(PlatformAbility::Payments));
        $this->assertCount(0, NotificationRecipients::ownersAndAdmins());
        $this->assertCount(0, NotificationRecipients::owners());
    }

    public function test_owners_and_admins_exclude_other_roles(): void
    {
        $owner = $this->userWithRole('teacher', ['is_owner' => 1]);
        $admin = $this->userWithRole('admin');
        $this->userWithRole('admin_staff');
        $this->userWithRole('teacher');

        $this->assertSame($this->ids([$owner, $admin]), $this->ids(NotificationRecipients::ownersAndAdmins()));
        $this->assertSame($this->ids([$owner]), $this->ids(NotificationRecipients::owners()));
    }

    public function test_teachers_of_returns_only_active_course_teachers(): void
    {
        $activeTeacher = $this->userWithRole('teacher');
        $pendingTeacher = $this->userWithRole('teacher', ['status' => 'pending']);

        $courses = [
            Course::factory()->create(['user_id' => $activeTeacher->id]),
            Course::factory()->create(['user_id' => $pendingTeacher->id]),
            Course::factory()->create(['user_id' => $activeTeacher->id]),
        ];

        $this->assertSame($this->ids([$activeTeacher]), $this->ids(NotificationRecipients::teachersOf($courses)));
    }

    public function test_combine_dedupes_and_leaves_out_the_actor(): void
    {
        $owner = $this->userWithRole('admin', ['is_owner' => 1]);
        $admin = $this->userWithRole('admin');

        $combined = NotificationRecipients::combine(
            $admin,
            NotificationRecipients::ownersAndAdmins(),
            NotificationRecipients::withAbility(PlatformAbility::Payments),
        );

        $this->assertSame($this->ids([$owner]), $this->ids($combined));
    }
}
