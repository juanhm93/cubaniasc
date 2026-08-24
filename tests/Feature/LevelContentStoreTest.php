<?php

namespace Tests\Feature;

use App\Models\Level;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LevelContentStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_create_level_content(): void
    {
        $level = Level::factory()->create();

        $response = $this->postJson(route('api.levels.contents.store', [
            'level' => $level->id,
        ]), [
            'name' => 'Enchufla',
        ]);

        $response->assertUnauthorized();
    }

    public function test_admin_users_can_create_level_content(): void
    {
        $this->actingAsAdmin();
        $level = Level::factory()->create();

        $response = $this->postJson(route('api.levels.contents.store', [
            'level' => $level->id,
        ]), [
            'name' => 'Enchufla',
            'description' => 'Giro básico',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $response->assertCreated();
        $response->assertJsonFragment([
            'name' => 'Enchufla',
            'description' => 'Giro básico',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'sort_order' => 1,
        ]);

        $this->assertDatabaseHas('level_contents', [
            'level_id' => $level->id,
            'name' => 'Enchufla',
        ]);
    }

    public function test_store_validates_required_name(): void
    {
        $this->actingAsAdmin();
        $level = Level::factory()->create();

        $response = $this->postJson(route('api.levels.contents.store', [
            'level' => $level->id,
        ]), [
            'name' => '',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name']);
    }

    private function actingAsAdmin(): User
    {
        $adminRole = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin',
        ]);
        $user = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);

        $this->actingAs($user);

        return $user;
    }
}
