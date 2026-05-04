<?php

namespace Tests\Feature;

use App\Models\Level;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LevelShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_admin_user_receives_level_id_as_inertia_prop(): void
    {
        $adminRole = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin',
        ]);
        $user = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);
        $level = Level::factory()->create();

        $this->actingAs($user);

        $this->get(route('levels.show', ['level' => $level->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('level')
                ->where('level', (string) $level->id));
    }

    public function test_admin_user_can_fetch_single_level_via_api(): void
    {
        $adminRole = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin',
        ]);
        $user = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);
        $level = Level::factory()->create();

        $this->actingAs($user);

        $this->getJson(route('api.levels.show', ['level' => $level->id]))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $level->id,
                'name' => $level->name,
            ]);
    }
}
