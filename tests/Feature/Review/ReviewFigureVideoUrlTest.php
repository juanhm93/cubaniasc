<?php

declare(strict_types=1);

namespace Tests\Feature\Review;

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\LevelContent;
use App\Models\ReviewSession;
use App\Models\Student;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReviewFigureVideoUrlTest extends TestCase
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

    public function test_a_figure_selected_in_an_active_session_gets_a_url_to_play_it(): void
    {
        $this->freezeTime();
        [$student, $level] = $this->createEnrolledStudent();
        $session = $this->createActiveSession($student, $level);
        $figure = $this->selectFigure($session, $level, ['name' => 'Dile que no']);
        Sanctum::actingAs($student);

        $response = $this->postJson(route('review.sessions.figures.video-url', [$session, $figure]))
            ->assertOk()
            ->assertJsonPath('data.expires_at', now()->addMinutes(7)->toIso8601String())
            ->assertJsonPath('data.size_bytes', $figure->video_file_size_bytes)
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.filename', 'dile-que-no-'.$figure->id.'-v1.mp4');

        $this->assertStringStartsWith('https://r2.test/'.$figure->video_file_path, $response->json('data.url'));
    }

    public function test_the_url_is_always_for_playing_never_for_downloading(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $session = $this->createActiveSession($student, $level);
        $figure = $this->selectFigure($session, $level, ['name' => 'Dile que no']);
        Sanctum::actingAs($student);

        $response = $this->postJson(route('review.sessions.figures.video-url', [$session, $figure]), [
            'purpose' => 'download',
        ])->assertOk();

        $this->assertSame(
            'inline; filename=dile-que-no-'.$figure->id.'-v1.mp4',
            $this->signedUrlQuery($response->json('data.url'))['disposition'],
        );
    }

    #[DataProvider('sessionDurations')]
    public function test_the_url_does_not_outlive_the_session(int $remainingSeconds, int $expectedMinutes): void
    {
        $this->freezeTime();
        [$student, $level] = $this->createEnrolledStudent();
        $session = $this->createActiveSession($student, $level, $remainingSeconds);
        $figure = $this->selectFigure($session, $level);
        Sanctum::actingAs($student);

        $response = $this->postJson(route('review.sessions.figures.video-url', [$session, $figure]))->assertOk();

        $this->assertSame(
            now()->addMinutes($expectedMinutes)->getTimestamp(),
            (int) $this->signedUrlQuery($response->json('data.url'))['expires'],
        );
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function sessionDurations(): array
    {
        return [
            'rounded up to the minute' => [125, 3],
            'at least one minute' => [20, 1],
            'never more than the stream limit' => [7200, 60],
        ];
    }

    public function test_guests_cannot_request_the_url(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $session = $this->createActiveSession($student, $level);
        $figure = $this->selectFigure($session, $level);

        $this->postJson(route('review.sessions.figures.video-url', [$session, $figure]))->assertUnauthorized();
    }

    public function test_staff_cannot_request_the_url(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $session = $this->createActiveSession($student, $level);
        $figure = $this->selectFigure($session, $level);
        $this->actingAs(User::factory()->create(), 'sanctum');

        $this->postJson(route('review.sessions.figures.video-url', [$session, $figure]))->assertForbidden();
    }

    public function test_a_student_cannot_use_another_students_session(): void
    {
        [$student] = $this->createEnrolledStudent();
        [$otherStudent, $otherLevel] = $this->createEnrolledStudent();
        $session = $this->createActiveSession($otherStudent, $otherLevel);
        $figure = $this->selectFigure($session, $otherLevel);
        Sanctum::actingAs($student);

        $this->postJson(route('review.sessions.figures.video-url', [$session, $figure]))->assertForbidden();
    }

    public function test_figures_offered_but_not_selected_have_no_url(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $session = $this->createActiveSession($student, $level);
        $offered = LevelContent::factory()->withVideoFile()->for($level)->create();
        $session->figures()->attach($offered->id, ['selected_by_student' => false]);
        $sameLevel = LevelContent::factory()->withVideoFile()->for($level)->create();
        Sanctum::actingAs($student);

        $this->postJson(route('review.sessions.figures.video-url', [$session, $offered]))->assertNotFound();
        $this->postJson(route('review.sessions.figures.video-url', [$session, $sameLevel]))->assertNotFound();
    }

    public function test_a_figure_without_its_own_video_has_no_url(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $session = $this->createActiveSession($student, $level);
        $figure = LevelContent::factory()->for($level)->create();
        $session->figures()->attach($figure->id, ['selected_by_student' => true]);
        Sanctum::actingAs($student);

        $this->postJson(route('review.sessions.figures.video-url', [$session, $figure]))->assertNotFound();
    }

    public function test_an_expired_session_gets_no_url(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $session = ReviewSession::factory()->expired()->create([
            'student_id' => $student->id,
            'level_id' => $level->id,
        ]);
        $figure = $this->selectFigure($session, $level);
        Sanctum::actingAs($student);

        $this->postJson(route('review.sessions.figures.video-url', [$session, $figure]))->assertStatus(410);
    }

    public function test_the_url_is_unavailable_when_the_bucket_is_not_configured(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $session = $this->createActiveSession($student, $level);
        $figure = $this->selectFigure($session, $level);
        config(['filesystems.disks.r2.bucket' => null]);
        Sanctum::actingAs($student);

        $this->postJson(route('review.sessions.figures.video-url', [$session, $figure]))
            ->assertStatus(503)
            ->assertExactJson(['message' => 'El almacenamiento de videos no está disponible.']);
    }

    public function test_requests_are_limited_per_minute(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $session = $this->createActiveSession($student, $level);
        $figure = $this->selectFigure($session, $level);
        Sanctum::actingAs($student);

        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->postJson(route('review.sessions.figures.video-url', [$session, $figure]))->assertOk();
        }

        $this->postJson(route('review.sessions.figures.video-url', [$session, $figure]))->assertTooManyRequests();
    }

    public function test_the_session_says_which_figures_have_their_own_video_without_exposing_it(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $session = $this->createActiveSession($student, $level);
        $figure = $this->selectFigure($session, $level);
        $youtubeOnly = LevelContent::factory()->for($level)->create(['video_url' => 'https://youtu.be/dQw4w9WgXcQ']);
        $session->figures()->attach($youtubeOnly->id, ['selected_by_student' => true]);
        Sanctum::actingAs($student);

        $response = $this->getJson(route('review.sessions.show', $session))
            ->assertOk()
            ->assertJsonPath('data.selected_figures.0.video.available', true)
            ->assertJsonPath('data.selected_figures.0.video.version', 1)
            ->assertJsonPath('data.selected_figures.1.video.available', false)
            ->assertJsonPath('data.selected_figures.1.video.size_bytes', null);

        $this->assertSame(
            ['available', 'size_bytes', 'duration_seconds', 'version'],
            array_keys($response->json('data.selected_figures.0.video')),
        );
        $this->assertStringNotContainsString($figure->video_file_path, $response->getContent());
    }

    private function createActiveSession(Student $student, Level $level, int $remainingSeconds = 420): ReviewSession
    {
        return ReviewSession::factory()->create([
            'student_id' => $student->id,
            'level_id' => $level->id,
            'started_at' => now(),
            'expires_at' => now()->addSeconds($remainingSeconds),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function selectFigure(ReviewSession $session, Level $level, array $attributes = []): LevelContent
    {
        $figure = LevelContent::factory()->withVideoFile()->for($level)->create($attributes)->fresh();
        $session->figures()->attach($figure->id, ['selected_by_student' => true]);

        return $figure;
    }

    /**
     * @return array<string, string>
     */
    private function signedUrlQuery(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }

    /**
     * @return array{0: Student, 1: Level}
     */
    private function createEnrolledStudent(): array
    {
        $level = Level::factory()->create();
        $course = Course::factory()->create([
            'level_id' => $level->id,
            'is_active' => true,
        ]);
        $student = Student::factory()->create();

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Active,
        ]);

        return [$student, $level];
    }
}
