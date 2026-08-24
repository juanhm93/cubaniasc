<?php

namespace Tests\Feature;

use App\Models\Level;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LevelShowTest extends TestCase
{
    use RefreshDatabase;

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
