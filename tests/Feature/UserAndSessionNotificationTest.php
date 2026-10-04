<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Level;
use App\Models\Place;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AccountApprovedNotification;
use App\Notifications\NewOneTimeSessionNotification;
use App\Notifications\NewPendingUserNotification;
use App\Notifications\TeacherAssignedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserAndSessionNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Notification::fake();
    }

    private function userWithRole(string $slug, array $attributes = []): User
    {
        $role = Role::query()->where('slug', $slug)->first() ?? Role::factory()->create(['slug' => $slug]);

        return User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
            ...$attributes,
        ]);
    }

    public function test_a_new_registration_alerts_whoever_can_approve_accounts(): void
    {
        $owner = $this->userWithRole('staff', ['is_owner' => 1]);
        $admin = $this->userWithRole('admin');
        $adminStaff = $this->userWithRole('admin_staff');

        $this->post(route('register.store'), [
            'name' => 'Nueva Profe',
            'email' => 'nueva.profe@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect();

        Notification::assertSentTo($owner, NewPendingUserNotification::class, function (NewPendingUserNotification $notification) use ($owner): bool {
            $payload = $notification->toDatabase($owner);

            return $payload['user_name'] === 'Nueva Profe'
                && $payload['user_email'] === 'nueva.profe@example.com'
                && str_contains($notification->toMail($owner)->render(), e(route('admin.users.index')));
        });
        Notification::assertSentTo($admin, NewPendingUserNotification::class);
        Notification::assertNotSentTo($adminStaff, NewPendingUserNotification::class);
    }

    public function test_activating_a_pending_account_emails_the_user(): void
    {
        $admin = $this->userWithRole('admin');
        $pending = $this->userWithRole('teacher', ['status' => 'pending']);

        $this->actingAs($admin)
            ->patch(route('admin.users.status.update', $pending), ['status' => 'active'])
            ->assertRedirect();

        Notification::assertSentTo($pending, AccountApprovedNotification::class, function (AccountApprovedNotification $notification) use ($pending): bool {
            return $notification->via($pending) === ['mail']
                && str_contains($notification->toMail($pending)->render(), e(route('login')));
        });
    }

    public function test_status_changes_that_are_not_an_approval_do_not_email(): void
    {
        $admin = $this->userWithRole('admin');
        $active = $this->userWithRole('teacher');
        $pending = $this->userWithRole('teacher', ['status' => 'pending']);

        $this->actingAs($admin)->patch(route('admin.users.status.update', $active), ['status' => 'active']);
        $this->actingAs($admin)->patch(route('admin.users.status.update', $active), ['status' => 'pending']);
        $this->actingAs($admin)->patch(route('admin.users.status.update', $pending), ['status' => 'pending']);

        Notification::assertNothingSent();
    }

    public function test_creating_a_course_tells_the_assigned_teacher(): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = $this->userWithRole('teacher', ['company_id' => $admin->company_id]);
        $place = Place::factory()->create(['company_id' => $admin->company_id]);

        $this->actingAs($admin)->post(route('admin.courses.store'), [
            'level_id' => Level::factory()->create()->id,
            'place_id' => $place->id,
            'user_id' => $teacher->id,
            'price' => 40,
            'is_active' => true,
            'slots' => [['weekday' => 2, 'starts_at' => '19:00', 'ends_at' => '20:00']],
        ])->assertRedirect();

        Notification::assertSentTo($teacher, TeacherAssignedNotification::class, function (TeacherAssignedNotification $notification) use ($teacher): bool {
            $payload = $notification->toDatabase($teacher);

            return $payload['subject_type'] === 'course'
                && $payload['when'] === 'Mar 19:00–20:00'
                && str_contains($notification->toMail($teacher)->render(), 'Mar 19:00–20:00');
        });
    }

    public function test_reassigning_a_course_tells_only_the_new_teacher(): void
    {
        $admin = $this->userWithRole('admin');
        $previousTeacher = $this->userWithRole('teacher', ['company_id' => $admin->company_id]);
        $newTeacher = $this->userWithRole('teacher', ['company_id' => $admin->company_id]);
        $course = Course::factory()->create(['company_id' => $admin->company_id, 'user_id' => $previousTeacher->id]);

        $this->actingAs($admin)->patch(route('admin.courses.update', $course), [
            'is_active' => true,
            'user_id' => $newTeacher->id,
            'place_id' => $course->place_id,
        ])->assertRedirect();

        $this->actingAs($admin)->patch(route('admin.courses.update', $course), [
            'is_active' => false,
            'user_id' => $newTeacher->id,
            'place_id' => $course->place_id,
        ])->assertRedirect();

        Notification::assertSentToTimes($newTeacher, TeacherAssignedNotification::class, 1);
        Notification::assertNotSentTo($previousTeacher, TeacherAssignedNotification::class);
    }

    public function test_teachers_assigning_themselves_are_not_notified(): void
    {
        $teacher = $this->userWithRole('admin');
        $place = Place::factory()->create(['company_id' => $teacher->company_id]);

        $this->actingAs($teacher)->post(route('admin.courses.store'), [
            'level_id' => Level::factory()->create()->id,
            'place_id' => $place->id,
            'user_id' => $teacher->id,
            'price' => 40,
            'is_active' => true,
            'slots' => [['weekday' => 2, 'starts_at' => '19:00', 'ends_at' => '20:00']],
        ])->assertRedirect();

        Notification::assertNothingSentTo($teacher);
    }

    public function test_a_new_workshop_tells_its_teacher_and_shows_in_the_staff_bell(): void
    {
        $actor = $this->userWithRole('admin_staff');
        $teacher = $this->userWithRole('teacher', ['company_id' => $actor->company_id]);
        $staff = $this->userWithRole('staff');
        $owner = $this->userWithRole('admin', ['is_owner' => 1]);

        $this->actingAs($actor)->post(route('admin.one-time-sessions.store'), [
            'type' => 'workshop',
            'name' => 'Taller de rueda',
            'starts_at' => '2026-11-14 16:00:00',
            'is_active' => true,
            'user_id' => $teacher->id,
        ])->assertRedirect(route('admin.one-time-sessions.index'));

        Notification::assertSentTo($teacher, TeacherAssignedNotification::class, function (TeacherAssignedNotification $notification) use ($teacher): bool {
            $payload = $notification->toDatabase($teacher);

            return $payload['subject_type'] === 'one_time_session'
                && $payload['kind_label'] === 'Taller'
                && $payload['when'] === '14/11/2026 16:00';
        });
        Notification::assertNotSentTo($teacher, NewOneTimeSessionNotification::class);

        foreach ([$staff, $owner] as $recipient) {
            Notification::assertSentTo($recipient, NewOneTimeSessionNotification::class, function (NewOneTimeSessionNotification $notification) use ($recipient): bool {
                return $notification->via($recipient) === ['database']
                    && $notification->toDatabase($recipient)['name'] === 'Taller de rueda';
            });
        }

        Notification::assertNotSentTo($actor, NewOneTimeSessionNotification::class);
    }
}
