<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\DanceType;
use App\Models\Level;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DanceTypeCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_create_dance_types(): void
    {
        $response = $this->postJson(route('api.dance-types.store'), [
            'name' => 'Bachata',
        ]);

        $response->assertUnauthorized();
    }

    public function test_admin_users_can_create_dance_types(): void
    {
        $user = $this->actingAsAdmin();

        $response = $this->postJson(route('api.dance-types.store'), [
            'name' => 'Bachata',
            'description' => 'Sensual and dominican',
        ]);

        $response->assertCreated();
        $response->assertJsonFragment([
            'name' => 'Bachata',
            'description' => 'Sensual and dominican',
            'slug' => 'bachata',
            'levels_count' => 0,
            'figures_count' => 0,
        ]);

        $this->assertDatabaseHas('dance_types', [
            'name' => 'Bachata',
            'slug' => 'bachata',
        ]);
    }

    public function test_store_validates_required_name(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson(route('api.dance-types.store'), [
            'name' => '',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_admin_users_can_update_dance_types(): void
    {
        $this->actingAsAdmin();
        $danceType = DanceType::factory()->create([
            'name' => 'Original',
            'description' => 'Old',
        ]);

        $response = $this->patchJson(route('api.dance-types.update', [
            'danceType' => $danceType->id,
        ]), [
            'name' => 'Salsa Casino',
            'description' => 'Rueda foundations',
        ]);

        $response->assertOk();
        $response->assertJsonFragment([
            'name' => 'Salsa Casino',
            'description' => 'Rueda foundations',
        ]);

        $this->assertSame('Salsa Casino', $danceType->fresh()?->name);
    }

    public function test_admin_users_can_delete_unused_dance_types(): void
    {
        $this->actingAsAdmin();
        $danceType = DanceType::factory()->create();
        $level = Level::factory()->for($danceType)->create();

        $response = $this->deleteJson(route('api.dance-types.destroy', [
            'danceType' => $danceType->id,
        ]));

        $response->assertNoContent();
        $this->assertSoftDeleted('dance_types', ['id' => $danceType->id]);
        $this->assertSoftDeleted('levels', ['id' => $level->id]);
    }

    public function test_cannot_delete_dance_type_used_by_a_course(): void
    {
        $this->actingAsAdmin();
        $danceType = DanceType::factory()->create();
        $level = Level::factory()->for($danceType)->create();
        Course::factory()->create(['level_id' => $level->id]);

        $response = $this->deleteJson(route('api.dance-types.destroy', [
            'danceType' => $danceType->id,
        ]));

        $response->assertUnprocessable();
        $this->assertNotSoftDeleted('dance_types', ['id' => $danceType->id]);
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
