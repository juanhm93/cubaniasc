<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UsersRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_admin_can_access_users_role_management_view(): void
    {
        $adminRole = Role::factory()->create(['name' => 'Admin', 'slug' => 'admin']);
        $user = User::factory()->create(['role_id' => $adminRole->id]);

        $this->actingAs($user);

        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users')
                ->has('users')
                ->has('roles')
                ->where('can_run_owner_maintenance', false));
    }

    public function test_non_admin_cannot_access_users_role_management_view(): void
    {
        $staffRole = Role::factory()->create(['name' => 'Staff', 'slug' => 'staff']);
        $user = User::factory()->create(['role_id' => $staffRole->id]);

        $this->actingAs($user);

        $this->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_admin_can_update_user_role(): void
    {
        $adminRole = Role::factory()->create(['name' => 'Admin', 'slug' => 'admin']);
        $teacherRole = Role::factory()->create(['name' => 'Teacher', 'slug' => 'teacher']);
        $staffRole = Role::factory()->create(['name' => 'Staff', 'slug' => 'staff']);

        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        $targetUser = User::factory()->create(['role_id' => $staffRole->id]);

        $this->actingAs($admin);

        $response = $this->patch(route('admin.users.role.update', ['user' => $targetUser->id]), [
            'role_id' => $teacherRole->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'role_id' => $teacherRole->id,
        ]);
    }

    public function test_admin_can_update_user_status(): void
    {
        $adminRole = Role::factory()->create(['name' => 'Admin', 'slug' => 'admin']);
        $staffRole = Role::factory()->create(['name' => 'Staff', 'slug' => 'staff']);

        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        $targetUser = User::factory()->create([
            'role_id' => $staffRole->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin);

        $response = $this->patch(route('admin.users.status.update', ['user' => $targetUser->id]), [
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'status' => 'active',
        ]);
    }

    public function test_admin_cannot_set_invalid_user_status(): void
    {
        $adminRole = Role::factory()->create(['name' => 'Admin', 'slug' => 'admin']);
        $staffRole = Role::factory()->create(['name' => 'Staff', 'slug' => 'staff']);

        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        $targetUser = User::factory()->create([
            'role_id' => $staffRole->id,
            'status' => 'active',
        ]);

        $this->actingAs($admin);

        $this->patch(route('admin.users.status.update', ['user' => $targetUser->id]), [
            'status' => 'banned',
        ])->assertSessionHasErrors('status');

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'status' => 'active',
        ]);
    }

    public function test_admin_cannot_update_owner_user_role(): void
    {
        $adminRole = Role::factory()->create(['name' => 'Admin', 'slug' => 'admin']);
        $teacherRole = Role::factory()->create(['name' => 'Teacher', 'slug' => 'teacher']);
        $staffRole = Role::factory()->create(['name' => 'Staff', 'slug' => 'staff']);

        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        $owner = User::factory()->create([
            'role_id' => $staffRole->id,
            'is_owner' => 1,
        ]);

        $this->actingAs($admin);

        $this->patch(route('admin.users.role.update', ['user' => $owner->id]), [
            'role_id' => $teacherRole->id,
        ])->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $owner->id,
            'role_id' => $staffRole->id,
        ]);
    }

    public function test_admin_cannot_update_owner_user_status(): void
    {
        $adminRole = Role::factory()->create(['name' => 'Admin', 'slug' => 'admin']);
        $staffRole = Role::factory()->create(['name' => 'Staff', 'slug' => 'staff']);

        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        $owner = User::factory()->create([
            'role_id' => $staffRole->id,
            'status' => 'pending',
            'is_owner' => 1,
        ]);

        $this->actingAs($admin);

        $this->patch(route('admin.users.status.update', ['user' => $owner->id]), [
            'status' => 'active',
        ])->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $owner->id,
            'status' => 'pending',
        ]);
    }
}
