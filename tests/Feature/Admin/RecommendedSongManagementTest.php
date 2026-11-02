<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Level;
use App\Models\RecommendedSong;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use League\Flysystem\UnableToCheckExistence;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecommendedSongManagementTest extends TestCase
{
    use RefreshDatabase;

    private const FILE = 'musica-salsa-casino/Gipsy Kings - Bamboleo.mp3';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        config(['filesystems.disks.r2.bucket' => 'cubania-videos-test']);
        Storage::fake('r2');
        Storage::disk('r2')->put(self::FILE, $this->mp3Bytes());
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
                ->where('songs.0.audio_file_path', $song->audio_file_path)
                ->where('songs.0.audio.available', true)
                ->where('songs.0.audio.format', 'mp3')
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

    public function test_teacher_creates_a_song_from_a_file_of_the_bucket(): void
    {
        $levels = Level::factory()->count(2)->create();

        $this->actingAs($this->createUserWithRole('teacher'));

        $this->post(route('admin.recommended-songs.store'), $this->validPayload($levels->pluck('id')->all()))
            ->assertRedirect(route('admin.recommended-songs.index'));

        $song = RecommendedSong::query()->where('title', 'Bamboleo')->firstOrFail();

        $this->assertTrue($song->is_active);
        $this->assertEqualsCanonicalizing($levels->pluck('id')->all(), $song->levels()->pluck('levels.id')->all());
        $this->assertSame(self::FILE, $song->audio_file_path);
        $this->assertSame(strlen($this->mp3Bytes()), $song->audio_file_size_bytes);
        $this->assertSame(201, $song->audio_file_duration_seconds);
        $this->assertSame(1, $song->audio_file_version);
        $this->assertNull($song->audio_or_link_url);
    }

    public function test_a_file_with_an_nfd_name_is_stored_exactly_as_the_bucket_has_it(): void
    {
        $key = "musica-salsa-casino/Dayme\u{0301} Arocena - La Rumba me llamo yo.mp3";
        Storage::disk('r2')->put($key, $this->mp3Bytes());
        $level = Level::factory()->create();

        $this->actingAs($this->createUserWithRole('teacher'));

        $this->post(route('admin.recommended-songs.store'), [
            ...$this->validPayload([$level->id]),
            'audio_file_path' => $key,
        ])->assertSessionHasNoErrors();

        $this->assertSame($key, RecommendedSong::query()->sole()->audio_file_path);
    }

    public function test_update_syncs_levels(): void
    {
        [$oldLevel, $newLevel] = Level::factory()->count(2)->create();
        $song = RecommendedSong::factory()->create(['audio_file_path' => self::FILE, 'audio_file_version' => 2]);
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
        $this->assertSame(2, $song->audio_file_version);
    }

    public function test_keeping_the_same_file_does_not_reach_the_bucket(): void
    {
        $level = Level::factory()->create();
        $song = RecommendedSong::factory()->create(['audio_file_path' => self::FILE]);
        Storage::disk('r2')->delete(self::FILE);

        $this->actingAs($this->createUserWithRole('admin'));

        $this->patch(route('admin.recommended-songs.update', $song), $this->validPayload([$level->id]))
            ->assertSessionHasNoErrors();
    }

    public function test_changing_the_file_raises_the_version_and_updates_its_data(): void
    {
        $level = Level::factory()->create();
        $song = RecommendedSong::factory()->create(['audio_file_version' => 3, 'audio_file_duration_seconds' => 99]);

        $this->actingAs($this->createUserWithRole('admin'));

        $this->patch(route('admin.recommended-songs.update', $song), $this->validPayload([$level->id]))
            ->assertSessionHasNoErrors();

        $song->refresh();

        $this->assertSame(self::FILE, $song->audio_file_path);
        $this->assertSame(4, $song->audio_file_version);
        $this->assertSame(201, $song->audio_file_duration_seconds);
    }

    public function test_an_old_song_gets_its_file_and_keeps_its_link(): void
    {
        $level = Level::factory()->create();
        $song = RecommendedSong::factory()->withoutAudioFile()->create();
        $link = $song->audio_or_link_url;

        $this->actingAs($this->createUserWithRole('admin'));

        $this->patch(route('admin.recommended-songs.update', $song), $this->validPayload([$level->id]))
            ->assertSessionHasNoErrors();

        $song->refresh();

        $this->assertTrue($song->hasAudioFile());
        $this->assertSame(1, $song->audio_file_version);
        $this->assertSame($link, $song->audio_or_link_url);
    }

    public function test_an_old_song_can_be_edited_without_a_file_only_while_it_stays_inactive(): void
    {
        $level = Level::factory()->create();
        $song = RecommendedSong::factory()->withoutAudioFile()->inactive()->create();
        $payload = [...$this->validPayload([$level->id]), 'audio_file_path' => null, 'is_active' => false];

        $this->actingAs($this->createUserWithRole('admin'));

        $this->patch(route('admin.recommended-songs.update', $song), $payload)->assertSessionHasNoErrors();
        $this->assertSame('Bamboleo', $song->fresh()->title);

        $this->patch(route('admin.recommended-songs.update', $song), [...$payload, 'is_active' => true])
            ->assertSessionHasErrors(['audio_file_path']);
    }

    public function test_a_song_with_a_file_cannot_lose_it(): void
    {
        $level = Level::factory()->create();
        $song = RecommendedSong::factory()->inactive()->create();

        $this->actingAs($this->createUserWithRole('admin'));

        $this->patch(route('admin.recommended-songs.update', $song), [
            ...$this->validPayload([$level->id]),
            'audio_file_path' => null,
            'is_active' => false,
        ])->assertSessionHasErrors(['audio_file_path']);
    }

    public function test_validation_requires_fields_and_at_least_one_level(): void
    {
        $this->actingAs($this->createUserWithRole('admin'));

        $this->post(route('admin.recommended-songs.store'), [
            'title' => '',
            'artist' => '',
            'audio_file_path' => '',
            'is_active' => true,
            'level_ids' => [],
        ])->assertSessionHasErrors(['title', 'artist', 'audio_file_path', 'level_ids']);

        $this->post(route('admin.recommended-songs.store'), $this->validPayload([999]))
            ->assertSessionHasErrors(['level_ids.0']);

        $this->assertDatabaseCount('recommended_songs', 0);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function keysOutsideTheLibrary(): array
    {
        return [
            'another folder' => ['salsa-casino/basico-1/guapea.mp3'],
            'the folder itself' => ['musica-salsa-casino'],
            'climbing out' => ['musica-salsa-casino/../salsa-casino/guapea.mp3'],
            'unsupported format' => ['musica-salsa-casino/video.mp4'],
            'an external link' => ['https://www.youtube.com/watch?v=abc123'],
        ];
    }

    #[DataProvider('keysOutsideTheLibrary')]
    public function test_only_files_of_the_music_folder_can_be_used(string $key): void
    {
        $level = Level::factory()->create();

        $this->actingAs($this->createUserWithRole('admin'));

        $this->post(route('admin.recommended-songs.store'), [...$this->validPayload([$level->id]), 'audio_file_path' => $key])
            ->assertSessionHasErrors(['audio_file_path' => 'Ese archivo no se puede usar.']);

        $this->assertDatabaseCount('recommended_songs', 0);
    }

    public function test_a_file_that_is_not_in_the_bucket_is_rejected(): void
    {
        $level = Level::factory()->create();

        $this->actingAs($this->createUserWithRole('admin'));

        $this->post(route('admin.recommended-songs.store'), [
            ...$this->validPayload([$level->id]),
            'audio_file_path' => 'musica-salsa-casino/no-existe.mp3',
        ])->assertSessionHasErrors(['audio_file_path' => 'No encontramos ese archivo en el bucket.']);

        $this->assertDatabaseCount('recommended_songs', 0);
    }

    public function test_a_file_that_is_not_really_audio_is_rejected_and_stays_in_the_bucket(): void
    {
        $key = 'musica-salsa-casino/en-realidad-es-un-video.mp3';
        Storage::disk('r2')->put($key, "\x00\x00\x00\x20ftypisom\x00\x00\x00\x00");
        $level = Level::factory()->create();

        $this->actingAs($this->createUserWithRole('admin'));

        $this->post(route('admin.recommended-songs.store'), [...$this->validPayload([$level->id]), 'audio_file_path' => $key])
            ->assertSessionHasErrors(['audio_file_path' => 'El archivo debe ser un audio MP3 o WAV.']);

        $this->assertDatabaseCount('recommended_songs', 0);
        Storage::disk('r2')->assertExists($key);
    }

    public function test_a_file_can_only_be_one_song(): void
    {
        $level = Level::factory()->create();
        RecommendedSong::factory()->create(['audio_file_path' => self::FILE]);

        $this->actingAs($this->createUserWithRole('admin'));

        $this->post(route('admin.recommended-songs.store'), $this->validPayload([$level->id]))
            ->assertSessionHasErrors(['audio_file_path' => 'Ese archivo ya es otra canción del catálogo.']);

        $this->assertDatabaseCount('recommended_songs', 1);
    }

    public function test_the_file_of_a_deleted_song_can_become_a_new_song(): void
    {
        $level = Level::factory()->create();
        RecommendedSong::factory()->create(['audio_file_path' => self::FILE])->delete();

        $this->actingAs($this->createUserWithRole('admin'));

        $this->post(route('admin.recommended-songs.store'), $this->validPayload([$level->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, RecommendedSong::query()->where('audio_file_path', self::FILE)->count());
    }

    public function test_a_bucket_outage_comes_back_as_a_field_error(): void
    {
        $level = Level::factory()->create();
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->andThrow(UnableToCheckExistence::forLocation(self::FILE));
        Storage::set('r2', $disk);

        $this->actingAs($this->createUserWithRole('admin'));

        $this->post(route('admin.recommended-songs.store'), $this->validPayload([$level->id]))
            ->assertSessionHasErrors(['audio_file_path' => 'No pudimos comprobar el archivo. Inténtalo de nuevo.']);

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
            'audio_file_path' => self::FILE,
            'duration_seconds' => 201,
            'is_active' => true,
            'level_ids' => $levelIds,
        ];
    }

    private function mp3Bytes(): string
    {
        return 'ID3'.str_repeat("\x00", 64);
    }
}
