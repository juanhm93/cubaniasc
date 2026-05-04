<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class OwnerMaintenanceActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_owner_can_clear_application_cache_via_admin_route(): void
    {
        Artisan::spy();

        $adminRole = Role::factory()->create(['name' => 'Admin', 'slug' => 'admin']);
        $owner = User::factory()->create([
            'role_id' => $adminRole->id,
            'is_owner' => 1,
        ]);

        $this->actingAs($owner);

        $response = $this->post(route('admin.maintenance.cache-clear'));

        $response->assertRedirect();
        Artisan::shouldHaveReceived('call')->with('optimize:clear')->once();
    }

    public function test_owner_can_run_migrations_via_admin_route(): void
    {
        Artisan::spy();

        $adminRole = Role::factory()->create(['name' => 'Admin', 'slug' => 'admin']);
        $owner = User::factory()->create([
            'role_id' => $adminRole->id,
            'is_owner' => 1,
        ]);

        $this->actingAs($owner);

        $response = $this->post(route('admin.maintenance.migrate'));

        $response->assertRedirect();
        Artisan::shouldHaveReceived('call')->with('migrate', ['--force' => true])->once();
    }

    public function test_non_owner_admin_cannot_clear_application_cache(): void
    {
        Artisan::spy();

        $adminRole = Role::factory()->create(['name' => 'Admin', 'slug' => 'admin']);
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'is_owner' => 0,
        ]);

        $this->actingAs($admin);

        $this->post(route('admin.maintenance.cache-clear'))->assertForbidden();

        Artisan::shouldNotHaveReceived('call');
    }

    public function test_non_owner_admin_cannot_run_migrations(): void
    {
        Artisan::spy();

        $adminRole = Role::factory()->create(['name' => 'Admin', 'slug' => 'admin']);
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'is_owner' => 0,
        ]);

        $this->actingAs($admin);

        $this->post(route('admin.maintenance.migrate'))->assertForbidden();

        Artisan::shouldNotHaveReceived('call');
    }

    public function test_users_page_includes_owner_maintenance_flag_for_owner(): void
    {
        $adminRole = Role::factory()->create(['name' => 'Admin', 'slug' => 'admin']);
        $owner = User::factory()->create([
            'role_id' => $adminRole->id,
            'is_owner' => 1,
        ]);

        $this->actingAs($owner);

        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_run_owner_maintenance', true));
    }
}
