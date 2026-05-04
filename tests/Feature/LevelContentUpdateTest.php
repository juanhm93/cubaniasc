<?php

namespace Tests\Feature;

use App\Models\Level;
use App\Models\LevelContent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LevelContentUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_update_level_content(): void
    {
        $level = Level::factory()->create();
        $content = LevelContent::factory()->for($level)->create();

        $response = $this->patchJson(route('api.level-contents.update', [
            'levelContent' => $content->id,
        ]), [
            'name' => 'Updated name',
            'description' => null,
            'video_url' => null,
        ]);

        $response->assertUnauthorized();
    }

    public function test_admin_users_can_update_level_content(): void
    {
        $adminRole = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin',
        ]);
        $user = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);
        $level = Level::factory()->create();
        $content = LevelContent::factory()->for($level)->create([
            'name' => 'Original',
            'description' => 'Old desc',
            'video_url' => null,
        ]);

        $this->actingAs($user);

        $response = $this->patchJson(route('api.level-contents.update', [
            'levelContent' => $content->id,
        ]), [
            'name' => 'Nueva figura',
            'description' => 'Nueva descripción',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'name' => 'Nueva figura',
                'description' => 'Nueva descripción',
                'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ]);

        $this->assertDatabaseHas('level_contents', [
            'id' => $content->id,
            'name' => 'Nueva figura',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
    }

    public function test_update_validates_video_url_format_when_present(): void
    {
        $adminRole = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin',
        ]);
        $user = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);
        $level = Level::factory()->create();
        $content = LevelContent::factory()->for($level)->create();

        $this->actingAs($user);

        $response = $this->patchJson(route('api.level-contents.update', [
            'levelContent' => $content->id,
        ]), [
            'name' => 'Valid name',
            'description' => null,
            'video_url' => 'not-a-valid-url',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['video_url']);
    }
}
