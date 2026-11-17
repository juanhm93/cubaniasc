<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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

        $response = $this->post(route('admin.maintenance.cache-clear'), ['password' => 'password']);

        $response->assertRedirect();
        Artisan::shouldHaveReceived('call')->with('optimize:clear', [])->once();
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

        $response = $this->post(route('admin.maintenance.migrate'), ['password' => 'password']);

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

    public function test_owner_can_open_plain_maintenance_page(): void
    {
        $adminRole = Role::factory()->create(['name' => 'Admin', 'slug' => 'admin']);
        $owner = User::factory()->create([
            'role_id' => $adminRole->id,
            'is_owner' => 1,
        ]);

        $this->actingAs($owner);

        $this->get(route('admin.maintenance.show'))
            ->assertOk()
            ->assertViewIs('maintenance')
            ->assertSee(route('admin.maintenance.migrate'), false);
    }

    public function test_non_owner_admin_cannot_open_maintenance_page(): void
    {
        $adminRole = Role::factory()->create(['name' => 'Admin', 'slug' => 'admin']);
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'is_owner' => 0,
        ]);

        $this->actingAs($admin);

        $this->get(route('admin.maintenance.show'))->assertForbidden();
    }

    public function test_maintenance_page_works_when_notifications_table_is_missing(): void
    {
        $adminRole = Role::factory()->create(['name' => 'Admin', 'slug' => 'admin']);
        $owner = User::factory()->create([
            'role_id' => $adminRole->id,
            'is_owner' => 1,
        ]);

        Schema::drop('notifications');
        DB::table('migrations')->where('migration', '2026_09_23_235854_create_notifications_table')->delete();

        $this->actingAs($owner);

        $this->get(route('admin.maintenance.show'))->assertOk();

        $this->from(route('admin.maintenance.show'))
            ->post(route('admin.maintenance.migrate'), ['password' => 'password'])
            ->assertRedirect(route('admin.maintenance.show'));

        $this->assertTrue(Schema::hasTable('notifications'));
    }

    public function test_maintenance_actions_require_the_owner_password(): void
    {
        Artisan::spy();
        $owner = $this->createOwner();

        $this->actingAs($owner)
            ->from(route('admin.maintenance.show'))
            ->post(route('admin.maintenance.migrate'))
            ->assertSessionHasErrors('password');

        $this->actingAs($owner)
            ->from(route('admin.maintenance.show'))
            ->post(route('admin.maintenance.cache-clear'), ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password');

        Artisan::shouldNotHaveReceived('call', ['migrate', ['--force' => true]]);
        Artisan::shouldNotHaveReceived('call', ['optimize:clear', []]);
    }

    public function test_maintenance_actions_are_rate_limited(): void
    {
        $owner = $this->createOwner();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->actingAs($owner)
                ->post(route('admin.maintenance.cache-clear'), ['password' => 'wrong-password'])
                ->assertSessionHasErrors('password');
        }

        $this->actingAs($owner)
            ->post(route('admin.maintenance.cache-clear'), ['password' => 'password'])
            ->assertTooManyRequests();
    }

    public function test_maintenance_actions_are_logged(): void
    {
        Artisan::spy();
        Log::spy();
        $owner = $this->createOwner();

        $this->actingAs($owner)
            ->post(route('admin.maintenance.cache-clear'), ['password' => 'password'])
            ->assertRedirect();

        Log::shouldHaveReceived('notice')
            ->with('Owner maintenance command started.', \Mockery::on(fn (array $context): bool => $context['command'] === 'optimize:clear' && $context['user_id'] === $owner->id))
            ->once();
    }

    public function test_a_failing_command_is_reported_without_a_server_error(): void
    {
        Artisan::shouldReceive('call')->andThrow(new \RuntimeException('boom'));
        $owner = $this->createOwner();

        $this->actingAs($owner)
            ->from(route('admin.maintenance.show'))
            ->post(route('admin.maintenance.migrate'), ['password' => 'password'])
            ->assertRedirect(route('admin.maintenance.show'))
            ->assertSessionHasErrors('maintenance');
    }

    public function test_maintenance_stays_reachable_for_an_unverified_owner_without_two_factor(): void
    {
        config(['fortify.require_admin_two_factor' => true]);
        $owner = $this->createOwner(['email_verified_at' => null]);

        $this->actingAs($owner)
            ->get(route('admin.maintenance.show'))
            ->assertOk();
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createOwner(array $attributes = []): User
    {
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);

        return User::factory()->create([
            'role_id' => $adminRole->id,
            'is_owner' => 1,
            ...$attributes,
        ]);
    }
}
