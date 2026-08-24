<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Level;
use App\Models\LevelContent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LevelDestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_delete_levels(): void
    {
        $level = Level::factory()->create();

        $this->deleteJson(route('api.levels.destroy', [
            'level' => $level->id,
        ]))->assertUnauthorized();
    }

    public function test_admin_users_can_delete_unused_levels(): void
    {
        $this->actingAsAdmin();
        $level = Level::factory()->create();
        $content = LevelContent::factory()->for($level)->create();

        $this->deleteJson(route('api.levels.destroy', [
            'level' => $level->id,
        ]))->assertNoContent();

        $this->assertSoftDeleted('levels', ['id' => $level->id]);
        $this->assertSoftDeleted('level_contents', ['id' => $content->id]);
    }

    public function test_cannot_delete_level_used_by_a_course(): void
    {
        $this->actingAsAdmin();
        $level = Level::factory()->create();
        Course::factory()->create(['level_id' => $level->id]);

        $this->deleteJson(route('api.levels.destroy', [
            'level' => $level->id,
        ]))->assertUnprocessable();

        $this->assertNotSoftDeleted('levels', ['id' => $level->id]);
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
