<?php

namespace Tests\Feature;

use App\Models\DanceType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DanceTypeReorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_reorder_dance_types(): void
    {
        $first = DanceType::factory()->create(['sort_order' => 1]);
        $second = DanceType::factory()->create(['sort_order' => 2]);

        $this->postJson(route('api.dance-types.reorder'), [
            'ids' => [$second->id, $first->id],
        ])->assertUnauthorized();
    }

    public function test_staff_users_cannot_reorder_dance_types(): void
    {
        $user = $this->createUserWithRole('staff');
        $first = DanceType::factory()->create(['sort_order' => 1]);
        $second = DanceType::factory()->create(['sort_order' => 2]);

        $this->actingAs($user);

        $this->postJson(route('api.dance-types.reorder'), [
            'ids' => [$second->id, $first->id],
        ])->assertForbidden();
    }

    public function test_admin_users_can_reorder_dance_types(): void
    {
        $this->actingAsAdmin();
        $first = DanceType::factory()->create(['sort_order' => 1]);
        $second = DanceType::factory()->create(['sort_order' => 2]);
        $third = DanceType::factory()->create(['sort_order' => 3]);

        $this->postJson(route('api.dance-types.reorder'), [
            'ids' => [$third->id, $first->id, $second->id],
        ])->assertNoContent();

        $this->assertSame(
            [$third->id, $first->id, $second->id],
            DanceType::query()->orderBy('sort_order')->pluck('id')->map(intval(...))->all()
        );
        $this->assertEqualsCanonicalizing(
            [1, 2, 3],
            DanceType::query()->pluck('sort_order')->all()
        );
    }

    public function test_reorder_does_not_reuse_sort_order_from_soft_deleted_dance_types(): void
    {
        $this->actingAsAdmin();
        $deleted = DanceType::factory()->create(['sort_order' => 1]);
        $deleted->delete();
        $first = DanceType::factory()->create(['sort_order' => 2]);
        $second = DanceType::factory()->create(['sort_order' => 3]);

        $this->postJson(route('api.dance-types.reorder'), [
            'ids' => [$second->id, $first->id],
        ])->assertNoContent();

        $this->assertSame(1, $deleted->fresh()?->sort_order);
        $this->assertSame(
            [$second->id, $first->id],
            DanceType::query()->orderBy('sort_order')->pluck('id')->map(intval(...))->all()
        );
    }

    public function test_reorder_rejects_incomplete_ids(): void
    {
        $this->actingAsAdmin();
        $first = DanceType::factory()->create(['sort_order' => 1]);
        DanceType::factory()->create(['sort_order' => 2]);

        $this->postJson(route('api.dance-types.reorder'), [
            'ids' => [$first->id],
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
