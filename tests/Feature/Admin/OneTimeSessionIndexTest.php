<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OneTimeSessionIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_cannot_view_special_classes_index(): void
    {
        $this->get(route('admin.one-time-sessions.index'))->assertRedirect();
    }

    public function test_admin_can_view_special_classes_index_sorted_by_newest_start_date(): void
    {
        $role = Role::factory()->create(['slug' => 'admin']);
        $company = Company::factory()->create();
        $admin = User::factory()->create([
            'status' => 'active',
            'company_id' => $company->id,
            'role_id' => $role->id,
        ]);

        DB::table('one_time_sessions')->insert([
            [
                'company_id' => $company->id,
                'place_id' => null,
                'user_id' => $admin->id,
                'type' => 'workshop',
                'name' => 'Taller Antiguo',
                'starts_at' => now()->subDays(10),
                'ends_at' => now()->subDays(10)->addHours(2),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => $company->id,
                'place_id' => null,
                'user_id' => $admin->id,
                'type' => 'event',
                'name' => 'Evento Reciente',
                'starts_at' => now()->subDay(),
                'ends_at' => now()->subDay()->addHours(2),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($admin);

        $this->get(route('admin.one-time-sessions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/one-time-sessions/index')
                ->has('sessions', 2)
                ->where('sessions.0.name', 'Evento Reciente')
                ->where('sessions.1.name', 'Taller Antiguo'));
    }

    public function test_admin_can_view_special_class_create_form(): void
    {
        $role = Role::factory()->create(['slug' => 'admin']);
        $admin = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $this->actingAs($admin);

        $this->get(route('admin.one-time-sessions.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/one-time-sessions/create')
                ->has('places')
                ->has('teachers'));
    }

    public function test_admin_can_store_special_class(): void
    {
        $role = Role::factory()->create(['slug' => 'admin']);
        $company = Company::factory()->create();
        $admin = User::factory()->create([
            'status' => 'active',
            'company_id' => $company->id,
            'role_id' => $role->id,
        ]);

        $teacher = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $this->actingAs($admin);

        $this->post(route('admin.one-time-sessions.store'), [
            'type' => 'workshop',
            'name' => 'Taller de Bachata',
            'description' => 'Clase especial de una sola oportunidad',
            'price' => 25,
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDay()->addHours(2)->format('Y-m-d H:i:s'),
            'capacity' => 30,
            'user_id' => $teacher->id,
            'is_active' => true,
            'notes' => 'Traer hidratación',
        ])->assertRedirect(route('admin.one-time-sessions.index'));

        $this->assertDatabaseHas('one_time_sessions', [
            'company_id' => $company->id,
            'type' => 'workshop',
            'name' => 'Taller de Bachata',
            'user_id' => $teacher->id,
            'is_active' => 1,
        ]);
    }
}
