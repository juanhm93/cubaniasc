<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Api\V1\Auth\VerifyLoginCodeController;
use App\Models\Course;
use App\Models\DanceType;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\RecommendedSong;
use App\Models\Student;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SongApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.disks.r2.bucket' => 'cubania-videos-test']);
        Storage::fake('r2');
        Storage::disk('r2')->buildTemporaryUrlsUsing(
            fn (string $path, DateTimeInterface $expiration, array $options): string => 'https://r2.test/'.$path.'?'.http_build_query([
                'expires' => $expiration->getTimestamp(),
                'disposition' => $options['ResponseContentDisposition'] ?? null,
            ]),
        );
    }

    public function test_the_library_has_the_songs_of_the_students_level_and_the_earlier_ones(): void
    {
        [$student, $earlier, $current, $later] = $this->createStudentWithLevels();
        $earlierSong = $this->songIn([$earlier], ['title' => 'A de antes']);
        $currentSong = $this->songIn([$current], ['title' => 'B de ahora']);
        $this->songIn([$later], ['title' => 'C de después']);

        $this->actingAsAppStudent($student)
            ->getJson(route('v1.songs.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $earlierSong->id)
            ->assertJsonPath('data.0.level_ids', [$earlier->id])
            ->assertJsonPath('data.1.id', $currentSong->id)
            ->assertJsonPath('data.1.audio.available', true)
            ->assertJsonPath('data.1.audio.format', 'mp3')
            ->assertJsonPath('meta.current_level_id', $current->id)
            ->assertJsonPath('meta.levels.0.id', $current->id)
            ->assertJsonPath('meta.levels.0.is_current', true)
            ->assertJsonPath('meta.levels.1.id', $earlier->id)
            ->assertJsonPath('meta.levels.1.is_current', false);
    }

    public function test_a_song_shared_with_a_higher_level_does_not_reveal_it(): void
    {
        [$student, , $current, $later] = $this->createStudentWithLevels();
        $this->songIn([$current, $later]);

        $this->actingAsAppStudent($student)
            ->getJson(route('v1.songs.index'))
            ->assertJsonPath('data.0.level_ids', [$current->id]);
    }

    public function test_the_library_leaves_out_inactive_songs_and_songs_without_a_file(): void
    {
        [$student, , $current] = $this->createStudentWithLevels();
        $playable = $this->songIn([$current]);
        RecommendedSong::factory()->inactive()->create()->levels()->attach($current);
        RecommendedSong::factory()->withoutAudioFile()->create()->levels()->attach($current);

        $this->actingAsAppStudent($student)
            ->getJson(route('v1.songs.index'))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $playable->id);
    }

    public function test_the_library_has_no_urls_or_object_keys(): void
    {
        [$student, , $current] = $this->createStudentWithLevels();
        $this->songIn([$current]);

        $response = $this->actingAsAppStudent($student)->getJson(route('v1.songs.index'))->assertOk();

        $this->assertStringNotContainsString('musica-salsa-casino', $response->getContent());
        $this->assertStringNotContainsString('r2.test', $response->getContent());
        $response->assertJsonMissingPath('data.0.audio_or_link_url');
    }

    public function test_without_an_active_enrollment_the_library_is_empty(): void
    {
        $student = Student::factory()->create();

        $this->actingAsAppStudent($student)
            ->getJson(route('v1.songs.index'))
            ->assertOk()
            ->assertExactJson(['data' => [], 'meta' => ['current_level_id' => null, 'levels' => []]]);
    }

    public function test_a_student_gets_a_url_to_save_a_song_of_their_levels(): void
    {
        $this->freezeTime();
        [$student, $earlier] = $this->createStudentWithLevels();
        $song = $this->songIn([$earlier], ['artist' => 'Los Van Van', 'title' => 'Temba']);

        $this->actingAsAppStudent($student)
            ->postJson(route('v1.songs.audio-url', $song), ['purpose' => 'download'])
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'url' => 'https://r2.test/'.$song->audio_file_path.'?'.http_build_query([
                        'expires' => now()->addMinutes(30)->getTimestamp(),
                        'disposition' => 'attachment; filename=los-van-van-temba.mp3',
                    ]),
                    'expires_at' => now()->addMinutes(30)->toIso8601String(),
                    'size_bytes' => $song->audio_file_size_bytes,
                    'version' => $song->audio_file_version,
                    'filename' => 'los-van-van-temba.mp3',
                ],
            ]);
    }

    public function test_a_student_gets_a_url_to_play_a_song_by_default(): void
    {
        $this->freezeTime();
        [$student, , $current] = $this->createStudentWithLevels();
        $song = $this->songIn([$current]);

        $response = $this->actingAsAppStudent($student)
            ->postJson(route('v1.songs.audio-url', $song))
            ->assertOk()
            ->assertJsonPath('data.expires_at', now()->addMinutes(60)->toIso8601String());

        $this->assertStringContainsString('disposition=inline', $response->json('data.url'));
    }

    public function test_a_song_outside_the_students_levels_is_forbidden(): void
    {
        [$student, , , $later] = $this->createStudentWithLevels();
        $song = $this->songIn([$later]);

        $this->actingAsAppStudent($student)
            ->postJson(route('v1.songs.audio-url', $song))
            ->assertForbidden();
    }

    public function test_without_an_active_enrollment_every_song_is_forbidden(): void
    {
        $song = $this->songIn([Level::factory()->create()]);

        $this->actingAsAppStudent(Student::factory()->create())
            ->postJson(route('v1.songs.audio-url', $song))
            ->assertForbidden();
    }

    public function test_access_is_checked_before_availability(): void
    {
        [$student, , , $later] = $this->createStudentWithLevels();
        $song = RecommendedSong::factory()->withoutAudioFile()->create();
        $song->levels()->attach($later);

        $this->actingAsAppStudent($student)
            ->postJson(route('v1.songs.audio-url', $song))
            ->assertForbidden();
    }

    public function test_an_inactive_song_or_one_without_a_file_is_not_found(): void
    {
        [$student, , $current] = $this->createStudentWithLevels();
        $inactive = RecommendedSong::factory()->inactive()->create();
        $withoutFile = RecommendedSong::factory()->withoutAudioFile()->create();
        $inactive->levels()->attach($current);
        $withoutFile->levels()->attach($current);

        $client = $this->actingAsAppStudent($student);

        $client->postJson(route('v1.songs.audio-url', $inactive))->assertNotFound();
        $client->postJson(route('v1.songs.audio-url', $withoutFile))->assertNotFound();
    }

    public function test_a_deleted_or_unknown_song_is_not_found(): void
    {
        [$student, , $current] = $this->createStudentWithLevels();
        $song = $this->songIn([$current]);
        $song->delete();

        $client = $this->actingAsAppStudent($student);

        $client->postJson(route('v1.songs.audio-url', $song))->assertNotFound();
        $client->postJson(route('v1.songs.audio-url', 999999))->assertNotFound();
    }

    public function test_an_unknown_purpose_is_rejected(): void
    {
        [$student, , $current] = $this->createStudentWithLevels();
        $song = $this->songIn([$current]);

        $this->actingAsAppStudent($student)
            ->postJson(route('v1.songs.audio-url', $song), ['purpose' => 'share'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['purpose']);
    }

    public function test_storage_outages_answer_service_unavailable(): void
    {
        [$student, , $current] = $this->createStudentWithLevels();
        $song = $this->songIn([$current]);
        config(['filesystems.disks.r2.bucket' => null]);

        $this->actingAsAppStudent($student)
            ->postJson(route('v1.songs.audio-url', $song))
            ->assertStatus(503)
            ->assertExactJson(['message' => 'El almacenamiento de música no está disponible.']);
    }

    public function test_the_url_is_limited_per_minute(): void
    {
        [$student, , $current] = $this->createStudentWithLevels();
        $song = $this->songIn([$current]);
        $client = $this->actingAsAppStudent($student);

        for ($request = 0; $request < 60; $request++) {
            $client->postJson(route('v1.songs.audio-url', $song))->assertOk();
        }

        $client->postJson(route('v1.songs.audio-url', $song))->assertTooManyRequests();
    }

    public function test_guests_and_tokens_without_the_app_ability_are_rejected(): void
    {
        [$student, , $current] = $this->createStudentWithLevels();
        $song = $this->songIn([$current]);

        $this->getJson(route('v1.songs.index'))->assertUnauthorized();
        $this->postJson(route('v1.songs.audio-url', $song))->assertUnauthorized();

        $token = $student->createToken('review-panel')->plainTextToken;

        $this->withToken($token)->getJson(route('v1.songs.index'))->assertForbidden();
        $this->withToken($token)->postJson(route('v1.songs.audio-url', $song))->assertForbidden();
    }

    public function test_the_examples_for_the_app_match_the_real_responses(): void
    {
        [$student, , $current] = $this->createStudentWithLevels();
        $song = $this->songIn([$current]);
        $client = $this->actingAsAppStudent($student);

        $this->assertSame(
            $this->shapeOf($this->example('songs.json')),
            $this->shapeOf($client->getJson(route('v1.songs.index'))->json()),
        );
        $this->assertSame(
            $this->shapeOf($this->example('song-audio-url.json')),
            $this->shapeOf($client->postJson(route('v1.songs.audio-url', $song))->json()),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function example(string $file): array
    {
        return json_decode((string) file_get_contents(base_path("docs/api/v1/examples/{$file}")), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * The keys of a response, with each list reduced to its first item.
     */
    private function shapeOf(mixed $value): mixed
    {
        if (! is_array($value)) {
            return null;
        }

        if (array_is_list($value)) {
            return $value === [] ? [] : [$this->shapeOf($value[0])];
        }

        ksort($value);

        return array_map(fn (mixed $item): mixed => $this->shapeOf($item), $value);
    }

    /**
     * @param  list<Level>  $levels
     * @param  array<string, mixed>  $attributes
     */
    private function songIn(array $levels, array $attributes = []): RecommendedSong
    {
        $song = RecommendedSong::factory()->create($attributes);
        $song->levels()->attach(array_map(fn (Level $level): int => $level->id, $levels));

        return $song->fresh();
    }

    private function actingAsAppStudent(Student $student): static
    {
        $token = $student->createToken(VerifyLoginCodeController::TOKEN_PREFIX.'Pixel 8', ['app'])->plainTextToken;

        return $this->withToken($token);
    }

    /**
     * @return array{0: Student, 1: Level, 2: Level, 3: Level}
     */
    private function createStudentWithLevels(): array
    {
        $danceType = DanceType::factory()->create();
        [$earlier, $current, $later] = collect([1, 2, 3])
            ->map(fn (int $sortOrder): Level => Level::factory()->create([
                'dance_type_id' => $danceType->id,
                'sort_order' => $sortOrder,
            ]))
            ->all();

        $course = Course::factory()->create(['level_id' => $current->id, 'is_active' => true]);
        $student = Student::factory()->create();

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Active,
            'enrolled_at' => now()->subMonth(),
        ]);

        return [$student, $earlier, $current, $later];
    }
}
