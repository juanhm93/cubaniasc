<?php

namespace Tests\Feature;

use App\Models\DanceType;
use App\Models\Level;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LevelReorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_reorder_levels(): void
    {
        $danceType = DanceType::factory()->create();
        $first = Level::factory()->for($danceType)->create(['sort_order' => 1]);
        $second = Level::factory()->for($danceType)->create(['sort_order' => 2]);

        $this->postJson(route('api.dance-types.levels.reorder', [
            'danceType' => $danceType->id,
        ]), [
            'ids' => [$second->id, $first->id],
        ])->assertUnauthorized();
    }

    public function test_admin_users_can_reorder_levels_within_a_dance_type(): void
    {
        $this->actingAsAdmin();
        $danceType = DanceType::factory()->create();
        $otherDanceType = DanceType::factory()->create();
        $first = Level::factory()->for($danceType)->create(['sort_order' => 1]);
        $second = Level::factory()->for($danceType)->create(['sort_order' => 2]);
        $third = Level::factory()->for($danceType)->create(['sort_order' => 3]);
        $other = Level::factory()->for($otherDanceType)->create(['sort_order' => 1]);

        $this->postJson(route('api.dance-types.levels.reorder', [
            'danceType' => $danceType->id,
        ]), [
            'ids' => [$third->id, $first->id, $second->id],
        ])->assertNoContent();

        $this->assertSame(
            [$third->id, $first->id, $second->id],
            Level::query()->where('dance_type_id', $danceType->id)->orderBy('sort_order')->pluck('id')->map(intval(...))->all()
        );
        $this->assertSame(1, $other->fresh()?->sort_order);
    }

    public function test_reorder_rejects_levels_from_another_dance_type(): void
    {
        $this->actingAsAdmin();
        $danceType = DanceType::factory()->create();
        $otherDanceType = DanceType::factory()->create();
        $first = Level::factory()->for($danceType)->create(['sort_order' => 1]);
        $second = Level::factory()->for($danceType)->create(['sort_order' => 2]);
        $other = Level::factory()->for($otherDanceType)->create(['sort_order' => 1]);

        $this->postJson(route('api.dance-types.levels.reorder', [
            'danceType' => $danceType->id,
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
