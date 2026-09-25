<?php

namespace Tests\Feature;

use App\Models\DanceType;
use App\Models\Level;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LevelStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_create_levels(): void
    {
        $response = $this->postJson(route('api.levels.store'), [
            'name' => 'Beginner',
            'description' => 'Intro course track',
        ]);

        $response->assertUnauthorized();
    }

    public function test_admin_users_can_create_levels(): void
    {
        $adminRole = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin',
        ]);
        $user = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);
        $danceType = DanceType::factory()->create();
        Level::factory()->create([
            'sort_order' => 3,
            'slug' => 'existing',
            'dance_type_id' => $danceType->id,
        ]);

        $this->actingAs($user);

        $response = $this->postJson(route('api.levels.store'), [
            'name' => 'Advanced',
            'description' => 'Next steps',
            'dance_type_id' => $danceType->id,
        ]);

        $response->assertCreated();
        $response->assertJsonFragment([
            'name' => 'Advanced',
            'description' => 'Next steps',
            'slug' => 'advanced',
            'sort_order' => 4,
        ]);

        $this->assertDatabaseHas('levels', [
            'name' => 'Advanced',
            'slug' => 'advanced',
            'sort_order' => 4,
            'dance_type_id' => $danceType->id,
        ]);
    }

    public function test_store_validates_required_name(): void
    {
        $adminRole = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin',
        ]);
        $user = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);
        $danceType = DanceType::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson(route('api.levels.store'), [
            'name' => '',
            'dance_type_id' => $danceType->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_store_validates_required_dance_type(): void
    {
        $adminRole = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin',
        ]);
        $user = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);
        $this->actingAs($user);

        $response = $this->postJson(route('api.levels.store'), [
            'name' => 'Advanced',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['dance_type_id']);
    }
}
