<?php

namespace Tests\Feature;

use App\Models\PreRegistration;
use App\Models\Role;
use App\Models\User;
use App\Notifications\NewPreRegistrationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function userWithRole(string $slug): User
    {
        $role = Role::query()->firstWhere('slug', $slug)
            ?? Role::factory()->create(['slug' => $slug]);

        return User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);
    }

    private function notify(User $user): void
    {
        $user->notify(new NewPreRegistrationNotification(PreRegistration::factory()->create()));
    }

    public function test_guest_cannot_reach_the_notification_endpoints(): void
    {
        $this->getJson(route('api.notifications.index'))->assertUnauthorized();
    }

    public function test_user_only_sees_their_own_notifications(): void
    {
        $admin = $this->userWithRole('admin');
        $other = $this->userWithRole('admin');

        $this->notify($admin);
        $this->notify($other);

        $this->actingAs($admin)
            ->getJson(route('api.notifications.index'))
            ->assertOk()
            ->assertJsonCount(1, 'notifications')
            ->assertJsonPath('unread_count', 1);
    }

    public function test_teacher_can_reach_the_notification_endpoints(): void
    {
        $teacher = $this->userWithRole('teacher');

        $this->notify($teacher);

        $this->actingAs($teacher)
            ->getJson(route('api.notifications.index'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1);
    }

    public function test_marking_a_notification_as_read_sets_read_at(): void
    {
        $admin = $this->userWithRole('admin');

        $this->notify($admin);

        $notification = $admin->notifications()->firstOrFail();

        $this->actingAs($admin)
            ->patchJson(route('api.notifications.read', ['notification' => $notification->id]))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $admin = $this->userWithRole('admin');
        $other = $this->userWithRole('admin');

        $this->notify($other);

        $notification = $other->notifications()->firstOrFail();

        $this->actingAs($admin)
            ->patchJson(route('api.notifications.read', ['notification' => $notification->id]))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $admin = $this->userWithRole('admin');

        $this->notify($admin);
        $this->notify($admin);

        $this->actingAs($admin)
            ->postJson(route('api.notifications.read-all'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertSame(0, $admin->unreadNotifications()->count());
    }

    public function test_unread_count_is_shared_with_inertia(): void
    {
        $admin = $this->userWithRole('admin');

        $this->notify($admin);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('notifications.unreadCount', 1));
    }
}
