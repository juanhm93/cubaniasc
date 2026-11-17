<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\ThrottleRegistration;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserAccessSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $teacherRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->adminRole = Role::factory()->create(['slug' => 'admin', 'name' => 'Admin']);
        $this->teacherRole = Role::factory()->create(['slug' => 'teacher', 'name' => 'Teacher']);
    }

    public function test_admin_can_manage_non_admin_users(): void
    {
        $admin = $this->userWithRole($this->adminRole);
        $teacher = $this->userWithRole($this->teacherRole);
        $staffRole = Role::factory()->create(['slug' => 'staff']);

        $this->actingAs($admin)
            ->patch(route('admin.users.role.update', $teacher), ['role_id' => $staffRole->id])
            ->assertRedirect();
        $this->actingAs($admin)
            ->patch(route('admin.users.status.update', $teacher), ['status' => 'pending'])
            ->assertRedirect();

        $teacher->refresh();
        $this->assertSame($staffRole->id, $teacher->role_id);
        $this->assertSame('pending', $teacher->status);
    }

    public function test_admin_cannot_grant_the_admin_role(): void
    {
        $admin = $this->userWithRole($this->adminRole);
        $teacher = $this->userWithRole($this->teacherRole);

        $this->actingAs($admin)
            ->patch(route('admin.users.role.update', $teacher), ['role_id' => $this->adminRole->id])
            ->assertForbidden();

        $this->assertSame($this->teacherRole->id, $teacher->fresh()->role_id);
    }

    public function test_admin_cannot_change_another_admin(): void
    {
        $admin = $this->userWithRole($this->adminRole);
        $otherAdmin = $this->userWithRole($this->adminRole);

        $this->actingAs($admin)
            ->patch(route('admin.users.role.update', $otherAdmin), ['role_id' => $this->teacherRole->id])
            ->assertForbidden();
        $this->actingAs($admin)
            ->patch(route('admin.users.status.update', $otherAdmin), ['status' => 'pending'])
            ->assertForbidden();

        $this->assertSame('active', $otherAdmin->fresh()->status);
    }

    public function test_nobody_changes_themselves_or_the_owner(): void
    {
        $owner = $this->userWithRole($this->adminRole, ['is_owner' => 1]);
        $admin = $this->userWithRole($this->adminRole);

        $this->actingAs($admin)
            ->patch(route('admin.users.status.update', $admin), ['status' => 'pending'])
            ->assertForbidden();
        $this->actingAs($admin)
            ->patch(route('admin.users.role.update', $owner), ['role_id' => $this->teacherRole->id])
            ->assertForbidden();
        $this->actingAs($owner)
            ->patch(route('admin.users.status.update', $owner), ['status' => 'pending'])
            ->assertForbidden();
    }

    public function test_owner_can_promote_and_demote_admins(): void
    {
        $owner = $this->userWithRole($this->adminRole, ['is_owner' => 1]);
        $teacher = $this->userWithRole($this->teacherRole);
        $admin = $this->userWithRole($this->adminRole);

        $this->actingAs($owner)
            ->patch(route('admin.users.role.update', $teacher), ['role_id' => $this->adminRole->id])
            ->assertRedirect();
        $this->actingAs($owner)
            ->patch(route('admin.users.role.update', $admin), ['role_id' => $this->teacherRole->id])
            ->assertRedirect();

        $this->assertSame($this->adminRole->id, $teacher->fresh()->role_id);
        $this->assertSame($this->teacherRole->id, $admin->fresh()->role_id);
    }

    public function test_users_page_only_offers_allowed_actions(): void
    {
        $admin = $this->userWithRole($this->adminRole);
        $otherAdmin = $this->userWithRole($this->adminRole);
        $teacher = $this->userWithRole($this->teacherRole);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('users', fn ($users): bool => collect($users)->firstWhere('id', $teacher->id)['can_manage'] === true
                    && collect($users)->firstWhere('id', $otherAdmin->id)['can_manage'] === false
                    && collect($users)->firstWhere('id', $admin->id)['can_manage'] === false)
                ->where('roles', fn ($roles): bool => collect($roles)->pluck('slug')->doesntContain('admin')));
    }

    public function test_privilege_columns_are_not_mass_assignable(): void
    {
        $user = User::create([
            'name' => 'Intruder',
            'email' => 'intruder@example.com',
            'password' => 'password',
            'is_owner' => 1,
            'role_id' => $this->adminRole->id,
            'status' => 'active',
            'company_id' => 99,
        ])->fresh();

        $this->assertFalse($user->isOwner());
        $this->assertNull($user->role_id);
        $this->assertNull($user->company_id);
        $this->assertSame('pending', $user->status);
    }

    public function test_shared_auth_user_only_exposes_what_the_ui_needs(): void
    {
        $admin = $this->userWithRole($this->adminRole, ['phone' => '+58 412 0000000']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.id', $admin->id)
                ->where('auth.user.role.slug', 'admin')
                ->missing('auth.user.role_id')
                ->missing('auth.user.company_id')
                ->missing('auth.user.status')
                ->missing('auth.user.phone'));
    }

    public function test_users_with_a_status_other_than_active_are_kept_out(): void
    {
        $suspended = $this->userWithRole($this->teacherRole, ['status' => 'suspended']);

        $this->actingAs($suspended)
            ->get(route('dashboard'))
            ->assertRedirect(route('account.pending'));
    }

    public function test_unverified_users_must_confirm_their_email(): void
    {
        $unverified = $this->userWithRole($this->teacherRole, ['email_verified_at' => null]);

        $this->actingAs($unverified)
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_registration_sends_a_verification_email_and_waits_for_approval(): void
    {
        Notification::fake();

        $this->post(route('register.store'), [
            'name' => 'New Teacher',
            'email' => 'new@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::query()->where('email', 'new@example.com')->sole();

        $this->assertSame('pending', $user->status);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_changing_the_email_requires_verifying_it_again(): void
    {
        Notification::fake();
        $teacher = $this->userWithRole($this->teacherRole);

        $this->actingAs($teacher)
            ->patch(route('profile.update'), ['name' => $teacher->name, 'email' => 'changed@example.com'])
            ->assertSessionHasNoErrors();

        $this->assertNull($teacher->fresh()->email_verified_at);
        Notification::assertSentTo($teacher, VerifyEmail::class);
    }

    public function test_registration_is_rate_limited_per_ip(): void
    {
        for ($attempt = 1; $attempt <= ThrottleRegistration::MAX_PER_IP_PER_HOUR; $attempt++) {
            $this->post(route('register.store'), [
                'name' => "Bot {$attempt}",
                'email' => "bot{$attempt}@example.com",
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);
            $this->post(route('logout'));
        }

        $this->post(route('register.store'), [
            'name' => 'Bot 6',
            'email' => 'bot6@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertTooManyRequests();

        $this->assertDatabaseMissing('users', ['email' => 'bot6@example.com']);
    }

    public function test_existing_active_users_are_marked_as_verified_by_the_migration(): void
    {
        $active = $this->userWithRole($this->teacherRole, ['email_verified_at' => null]);
        $pending = $this->userWithRole($this->teacherRole, ['email_verified_at' => null, 'status' => 'pending']);

        $migration = require database_path('migrations/2026_11_17_050543_mark_existing_active_users_as_verified.php');
        $migration->up();

        $this->assertNotNull($active->fresh()->email_verified_at);
        $this->assertNull($pending->fresh()->email_verified_at);
    }

    public function test_admins_without_two_factor_are_sent_to_the_security_page(): void
    {
        config(['fortify.require_admin_two_factor' => true]);
        $admin = $this->userWithRole($this->adminRole);
        $owner = $this->userWithRole($this->adminRole, ['is_owner' => 1]);

        foreach ([$admin, $owner] as $user) {
            $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('security.edit'));
            $this->actingAs($user)->getJson(route('api.notifications.index'))->assertForbidden();
            $this->actingAs($user)->get(route('security.edit'))->assertRedirect(route('password.confirm'));
        }
    }

    public function test_admins_with_two_factor_and_other_roles_use_the_panel(): void
    {
        config(['fortify.require_admin_two_factor' => true]);
        $adminWithTwoFactor = $this->userWithRole($this->adminRole);
        $adminWithTwoFactor->forceFill([
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['code-1'])),
            'two_factor_confirmed_at' => now(),
        ])->save();
        $teacher = $this->userWithRole($this->teacherRole);

        $this->actingAs($adminWithTwoFactor)->get(route('dashboard'))->assertOk();
        $this->actingAs($teacher)->get(route('dashboard'))->assertOk();
    }

    public function test_the_two_factor_requirement_can_be_turned_off(): void
    {
        config(['fortify.require_admin_two_factor' => false]);

        $this->actingAs($this->userWithRole($this->adminRole))
            ->get(route('dashboard'))
            ->assertOk();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function userWithRole(Role $role, array $attributes = []): User
    {
        return User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
            ...$attributes,
        ]);
    }
}
