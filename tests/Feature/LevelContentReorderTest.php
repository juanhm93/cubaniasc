<?php

namespace Tests\Feature;

use App\Models\Level;
use App\Models\LevelContent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LevelContentReorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_reorder_level_contents(): void
    {
        $level = Level::factory()->create();
        $first = LevelContent::factory()->for($level)->create(['sort_order' => 1]);
        $second = LevelContent::factory()->for($level)->create(['sort_order' => 2]);

        $this->postJson(route('api.levels.contents.reorder', [
            'level' => $level->id,
        ]), [
            'ids' => [$second->id, $first->id],
        ])->assertUnauthorized();
    }

    public function test_admin_users_can_reorder_figures_within_a_level(): void
    {
        $this->actingAsAdmin();
        $level = Level::factory()->create();
        $otherLevel = Level::factory()->create();
        $first = LevelContent::factory()->for($level)->create(['sort_order' => 1]);
        $second = LevelContent::factory()->for($level)->create(['sort_order' => 2]);
        $third = LevelContent::factory()->for($level)->create(['sort_order' => 3]);
        $other = LevelContent::factory()->for($otherLevel)->create(['sort_order' => 1]);

        $this->postJson(route('api.levels.contents.reorder', [
            'level' => $level->id,
        ]), [
            'ids' => [$third->id, $first->id, $second->id],
        ])->assertNoContent();

        $this->assertSame(
            [$third->id, $first->id, $second->id],
            $level->levelContents()->orderBy('sort_order')->pluck('id')->map(intval(...))->all()
        );
        $this->assertSame(1, $other->fresh()?->sort_order);
    }

    public function test_reorder_rejects_figures_from_another_level(): void
    {
        $this->actingAsAdmin();
        $level = Level::factory()->create();
        $otherLevel = Level::factory()->create();
        $first = LevelContent::factory()->for($level)->create(['sort_order' => 1]);
        $second = LevelContent::factory()->for($level)->create(['sort_order' => 2]);
        $other = LevelContent::factory()->for($otherLevel)->create(['sort_order' => 1]);

        $this->postJson(route('api.levels.contents.reorder', [
            'level' => $level->id,
        ]), [
            'ids' => [$other->id, $first->id, $second->id],
        ])->assertUnprocessable();
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
