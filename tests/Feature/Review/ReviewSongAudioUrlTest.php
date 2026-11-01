<?php

declare(strict_types=1);

namespace Tests\Feature\Review;

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\RecommendedSong;
use App\Models\ReviewSession;
use App\Models\Student;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewSongAudioUrlTest extends TestCase
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

    public function test_a_song_of_the_session_gets_a_url_to_play_it_by_default(): void
    {
        $this->freezeTime();
        [$student, $session] = $this->createSession();
        $song = $this->assignSong($session, ['artist' => 'Los Van Van', 'title' => 'Temba']);
        Sanctum::actingAs($student);

        $response = $this->postJson(route('review.sessions.songs.audio-url', [$session, $song]))
            ->assertOk()
            ->assertJsonPath('data.expires_at', now()->addMinutes(60)->toIso8601String())
            ->assertJsonPath('data.size_bytes', $song->audio_file_size_bytes)
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.filename', 'los-van-van-temba.mp3');

        $this->assertStringStartsWith('https://r2.test/'.$song->audio_file_path, $response->json('data.url'));
        $this->assertSame('inline; filename=los-van-van-temba.mp3', $this->signedUrlQuery($response->json('data.url'))['disposition']);
    }

    public function test_the_student_can_download_a_song_of_the_session(): void
    {
        $this->freezeTime();
        [$student, $session] = $this->createSession();
        $song = $this->assignSong($session, ['artist' => 'Los Van Van', 'title' => 'Temba']);
        Sanctum::actingAs($student);

        $response = $this->postJson(route('review.sessions.songs.audio-url', [$session, $song]), ['purpose' => 'download'])
            ->assertOk()
            ->assertJsonPath('data.expires_at', now()->addMinutes(30)->toIso8601String());

        $this->assertSame('attachment; filename=los-van-van-temba.mp3', $this->signedUrlQuery($response->json('data.url'))['disposition']);
    }

    public function test_an_unknown_purpose_is_rejected(): void
    {
        [$student, $session] = $this->createSession();
        $song = $this->assignSong($session);
        Sanctum::actingAs($student);

        $this->postJson(route('review.sessions.songs.audio-url', [$session, $song]), ['purpose' => 'share'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['purpose']);
    }

    public function test_music_still_works_after_the_timer_runs_out(): void
    {
        [$student, $session] = $this->createSession(['expires_at' => now()->subHour()]);
        $song = $this->assignSong($session);
        Sanctum::actingAs($student);

        $this->assertTrue(now()->isAfter($session->expires_at));

        $this->postJson(route('review.sessions.songs.audio-url', [$session, $song]))->assertOk();
    }

    public function test_guests_cannot_request_the_url(): void
    {
        [, $session] = $this->createSession();
        $song = $this->assignSong($session);

        $this->postJson(route('review.sessions.songs.audio-url', [$session, $song]))->assertUnauthorized();
    }

    public function test_staff_cannot_request_the_url(): void
    {
        [, $session] = $this->createSession();
        $song = $this->assignSong($session);
        $this->actingAs(User::factory()->create(), 'sanctum');

        $this->postJson(route('review.sessions.songs.audio-url', [$session, $song]))->assertForbidden();
    }

    public function test_a_student_cannot_use_another_students_session(): void
    {
        [$student] = $this->createSession();
        [, $otherSession] = $this->createSession();
        $song = $this->assignSong($otherSession);
        Sanctum::actingAs($student);

        $this->postJson(route('review.sessions.songs.audio-url', [$otherSession, $song]))->assertForbidden();
    }

    public function test_a_song_outside_the_session_has_no_url(): void
    {
        [$student, $session] = $this->createSession();
        $song = RecommendedSong::factory()->create();
        $song->levels()->attach($session->level_id);
        Sanctum::actingAs($student);

        $this->postJson(route('review.sessions.songs.audio-url', [$session, $song]))->assertNotFound();
    }

    public function test_a_session_song_without_a_file_has_no_url(): void
    {
        [$student, $session] = $this->createSession();
        $song = RecommendedSong::factory()->withoutAudioFile()->create();
        $session->songs()->attach($song);
        Sanctum::actingAs($student);

        $this->postJson(route('review.sessions.songs.audio-url', [$session, $song]))->assertNotFound();
    }

    public function test_the_url_is_unavailable_when_the_bucket_is_not_configured(): void
    {
        [$student, $session] = $this->createSession();
        $song = $this->assignSong($session);
        config(['filesystems.disks.r2.bucket' => null]);
        Sanctum::actingAs($student);

        $this->postJson(route('review.sessions.songs.audio-url', [$session, $song]))
            ->assertStatus(503)
            ->assertExactJson(['message' => 'El almacenamiento de música no está disponible.']);
    }

    public function test_the_url_is_limited_per_minute(): void
    {
        [$student, $session] = $this->createSession();
        $song = $this->assignSong($session);
        Sanctum::actingAs($student);

        for ($request = 0; $request < 60; $request++) {
            $this->postJson(route('review.sessions.songs.audio-url', [$session, $song]))->assertOk();
        }

        $this->postJson(route('review.sessions.songs.audio-url', [$session, $song]))->assertTooManyRequests();
    }

    public function test_the_session_songs_say_whether_they_can_be_played_and_hide_the_key(): void
    {
        [$student, $session] = $this->createSession();
        $song = $this->assignSong($session, ['audio_file_duration_seconds' => 240]);
        Sanctum::actingAs($student);

        $response = $this->getJson(route('review.sessions.songs', $session))
            ->assertOk()
            ->assertJsonPath('data.0.id', $song->id)
            ->assertJsonPath('data.0.audio.available', true)
            ->assertJsonPath('data.0.audio.format', 'mp3')
            ->assertJsonPath('data.0.audio.duration_seconds', 240)
            ->assertJsonMissingPath('data.0.audio_or_link_url');

        $this->assertStringNotContainsString('musica-salsa-casino', $response->getContent());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assignSong(ReviewSession $session, array $attributes = []): RecommendedSong
    {
        $song = RecommendedSong::factory()->create($attributes);
        $song->levels()->attach($session->level_id);
        $session->songs()->attach($song);

        return $song->fresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0: Student, 1: ReviewSession}
     */
    private function createSession(array $attributes = []): array
    {
        $level = Level::factory()->create();
        $course = Course::factory()->create(['level_id' => $level->id, 'is_active' => true]);
        $student = Student::factory()->create();

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Active,
        ]);

        return [$student, ReviewSession::factory()->create([
            'student_id' => $student->id,
            'level_id' => $level->id,
            'started_at' => now()->subHours(2),
            'expires_at' => now()->addMinutes(10),
            ...$attributes,
        ])];
    }

    /**
     * @return array<string, string>
     */
    private function signedUrlQuery(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }
}
