<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Level;
use App\Models\RecommendedSong;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecommendedSongManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_is_redirected_from_recommended_songs(): void
    {
        $this->get(route('admin.recommended-songs.index'))->assertRedirect();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rolesWithContentAbility(): array
    {
        return [
            'admin' => ['admin'],
            'teacher' => ['teacher'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rolesWithoutContentAbility(): array
    {
        return [
            'admin_staff' => ['admin_staff'],
            'staff' => ['staff'],
        ];
    }

    #[DataProvider('rolesWithContentAbility')]
    public function test_content_roles_can_list_songs(string $slug): void
    {
        $level = Level::factory()->create(['name' => 'Básico 1']);
        $song = RecommendedSong::factory()->create(['title' => 'El Cuarto de Tula']);
        $song->levels()->attach($level);

        $this->actingAs($this->createUserWithRole($slug));

        $this->get(route('admin.recommended-songs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/recommended-songs/index')
                ->has('songs', 1)
                ->where('songs.0.title', 'El Cuarto de Tula')
                ->where('songs.0.levels.0.name', 'Básico 1')
                ->has('danceTypes', 1)
            );
    }

    #[DataProvider('rolesWithoutContentAbility')]
    public function test_roles_without_content_ability_cannot_manage_songs(string $slug): void
    {
        $song = RecommendedSong::factory()->create(['title' => 'Original']);
        $level = Level::factory()->create();

        $this->actingAs($this->createUserWithRole($slug));

        $this->get(route('admin.recommended-songs.index'))->assertForbidden();
        $this->post(route('admin.recommended-songs.store'), $this->validPayload([$level->id]))->assertForbidden();
        $this->patch(route('admin.recommended-songs.update', $song), $this->validPayload([$level->id]))->assertForbidden();
        $this->delete(route('admin.recommended-songs.destroy', $song))->assertForbidden();

        $this->assertSame('Original', $song->fresh()?->title);
        $this->assertNotSoftDeleted($song);
    }

    public function test_teacher_creates_a_song_assigned_to_levels(): void
    {
        $levels = Level::factory()->count(2)->create();

        $this->actingAs($this->createUserWithRole('teacher'));

        $this->post(route('admin.recommended-songs.store'), $this->validPayload($levels->pluck('id')->all()))
            ->assertRedirect(route('admin.recommended-songs.index'));

        $song = RecommendedSong::query()->where('title', 'Bamboleo')->firstOrFail();

        $this->assertTrue($song->is_active);
        $this->assertEqualsCanonicalizing($levels->pluck('id')->all(), $song->levels()->pluck('levels.id')->all());
    }

    public function test_update_syncs_levels(): void
    {
        [$oldLevel, $newLevel] = Level::factory()->count(2)->create();
        $song = RecommendedSong::factory()->create();
        $song->levels()->attach($oldLevel);

        $this->actingAs($this->createUserWithRole('admin'));

        $this->patch(route('admin.recommended-songs.update', $song), [
            ...$this->validPayload([$newLevel->id]),
            'title' => 'Nuevo título',
            'is_active' => false,
        ])->assertRedirect(route('admin.recommended-songs.index'));

        $song->refresh();

        $this->assertSame('Nuevo título', $song->title);
        $this->assertFalse($song->is_active);
        $this->assertSame([$newLevel->id], $song->levels()->pluck('levels.id')->all());
    }

    public function test_validation_requires_fields_and_at_least_one_level(): void
    {
        $this->actingAs($this->createUserWithRole('admin'));

        $this->post(route('admin.recommended-songs.store'), [
            'title' => '',
            'artist' => '',
            'audio_or_link_url' => 'no-es-un-enlace',
            'is_active' => true,
            'level_ids' => [],
        ])->assertSessionHasErrors(['title', 'artist', 'audio_or_link_url', 'level_ids']);

        $this->post(route('admin.recommended-songs.store'), $this->validPayload([999]))
            ->assertSessionHasErrors(['level_ids.0']);

        $this->assertDatabaseCount('recommended_songs', 0);
    }

    public function test_destroy_soft_deletes_the_song_and_hides_it_from_the_review(): void
    {
        $song = RecommendedSong::factory()->create();

        $this->actingAs($this->createUserWithRole('admin'));

        $this->delete(route('admin.recommended-songs.destroy', $song))
            ->assertRedirect(route('admin.recommended-songs.index'));

        $this->assertSoftDeleted($song);
    }

    /**
     * @param  list<int>  $levelIds
     * @return array<string, mixed>
     */
    private function validPayload(array $levelIds): array
    {
        return [
            'title' => 'Bamboleo',
            'artist' => 'Gipsy Kings',
            'audio_or_link_url' => 'https://www.youtube.com/watch?v=abc123',
            'is_active' => true,
            'level_ids' => $levelIds,
        ];
    }
}
