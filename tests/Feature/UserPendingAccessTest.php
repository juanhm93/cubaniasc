<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPendingAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_pending_user_is_redirected_from_dashboard_to_pending_page(): void
    {
        $user = User::factory()->create(['status' => 'pending']);

        $this->actingAs($user);

        $this->get(route('dashboard'))
            ->assertRedirect(route('account.pending'));
    }

    public function test_pending_user_can_view_pending_account_page(): void
    {
        $user = User::factory()->create(['status' => 'pending']);

        $this->actingAs($user);

        $this->get(route('account.pending'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('account/pending'));
    }

    public function test_active_user_visiting_pending_page_is_redirected_to_dashboard(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user);

        $this->get(route('account.pending'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_pending_user_can_log_out(): void
    {
        $user = User::factory()->create(['status' => 'pending']);

        $this->actingAs($user);

        $this->post(route('logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_pending_user_cannot_access_admin_users(): void
    {
        $staffRole = Role::factory()->create(['name' => 'Staff', 'slug' => 'staff']);
        $user = User::factory()->create([
            'status' => 'pending',
            'role_id' => $staffRole->id,
        ]);

        $this->actingAs($user);

        $this->get(route('admin.users.index'))
            ->assertRedirect(route('account.pending'));
    }
}
