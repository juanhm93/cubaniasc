<?php

namespace Tests\Feature;

use App\Models\Level;
use App\Models\LevelContent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LevelContentDestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_delete_level_content(): void
    {
        $level = Level::factory()->create();
        $content = LevelContent::factory()->for($level)->create();

        $response = $this->deleteJson(route('api.level-contents.destroy', [
            'levelContent' => $content->id,
        ]));

        $response->assertUnauthorized();
    }

    public function test_admin_users_can_soft_delete_level_content(): void
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

        $response = $this->deleteJson(route('api.level-contents.destroy', [
            'levelContent' => $content->id,
        ]));

        $response->assertNoContent();

        $this->assertSoftDeleted('level_contents', [
            'id' => $content->id,
        ]);
    }

    public function test_non_admin_users_cannot_delete_level_content(): void
    {
        $teacherRole = Role::factory()->create([
            'name' => 'Teacher',
            'slug' => 'teacher',
        ]);
        $user = User::factory()->create([
            'role_id' => $teacherRole->id,
        ]);
        $level = Level::factory()->create();
        $content = LevelContent::factory()->for($level)->create();

        $this->actingAs($user);

        $this->deleteJson(route('api.level-contents.destroy', [
            'levelContent' => $content->id,
        ]))->assertForbidden();

        $this->assertNotSoftDeleted('level_contents', [
            'id' => $content->id,
        ]);
    }
}
