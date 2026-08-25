<?php

namespace Tests\Feature;

use App\Models\DanceType;
use App\Models\Level;
use App\Models\LevelContent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContentPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guests_cannot_view_content_index(): void
    {
        $this->get(route('content.index'))->assertRedirect();
    }

    public function test_levels_from_different_dance_types_can_share_sort_order(): void
    {
        $salsa = DanceType::factory()->create();
        $bachata = DanceType::factory()->create();

        Level::factory()->for($salsa)->create(['sort_order' => 1]);
        Level::factory()->for($bachata)->create(['sort_order' => 1]);

        $this->assertSame(2, Level::query()->where('sort_order', 1)->count());
    }

    public function test_admin_can_view_content_index_with_dance_types(): void
    {
        $this->actingAsAdmin();
        $danceType = DanceType::factory()->create(['name' => 'Salsa Casino']);
        Level::factory()->for($danceType)->count(2)->create();

        $this->get(route('content.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('content/index')
                ->has('danceTypes', 1)
                ->where('danceTypes.0.name', 'Salsa Casino')
                ->where('danceTypes.0.levels_count', 2));
    }

    public function test_admin_can_view_dance_type_detail_with_levels_and_figures(): void
    {
        $this->actingAsAdmin();
        $danceType = DanceType::factory()->create(['name' => 'Salsa Casino']);
        $level = Level::factory()->for($danceType)->create(['name' => 'Básico 1']);
        LevelContent::factory()->for($level)->create(['name' => 'Ángulos']);

        $this->get(route('content.show', ['danceType' => $danceType->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('content/show')
                ->where('danceType.name', 'Salsa Casino')
                ->has('danceType.levels', 1)
                ->where('danceType.levels.0.name', 'Básico 1')
                ->where('danceType.levels.0.level_contents.0.name', 'Ángulos'));
    }

    public function test_admin_can_view_level_detail_nested_under_dance_type(): void
    {
        $this->actingAsAdmin();
        $danceType = DanceType::factory()->create();
        $level = Level::factory()->for($danceType)->create(['name' => 'Básico 1']);

        $this->get(route('content.levels.show', [
            'danceType' => $danceType->id,
            'level' => $level->id,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('content/level')
                ->where('level.name', 'Básico 1')
                ->where('danceType.id', $danceType->id)
                ->where('danceType.name', $danceType->name));
    }

    public function test_level_from_another_dance_type_is_not_found(): void
    {
        $this->actingAsAdmin();
        $salsa = DanceType::factory()->create();
        $bachata = DanceType::factory()->create();
        $level = Level::factory()->for($bachata)->create();

        $this->get(route('content.levels.show', [
            'danceType' => $salsa->id,
            'level' => $level->id,
        ]))->assertNotFound();
    }

    public function test_legacy_levels_index_redirects_to_content(): void
    {
        $this->actingAsAdmin();

        $this->get(route('levels'))
            ->assertRedirect(route('content.index'));
    }

    public function test_legacy_level_show_redirects_to_nested_content_level(): void
    {
        $this->actingAsAdmin();
        $danceType = DanceType::factory()->create();
        $level = Level::factory()->for($danceType)->create();

        $this->get(route('levels.show', ['level' => $level->id]))
            ->assertRedirect(route('content.levels.show', [
                'danceType' => $danceType->id,
                'level' => $level->id,
            ]));
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
