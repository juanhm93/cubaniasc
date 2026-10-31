<?php

namespace Tests\Feature\Review;

use App\Enums\ReviewTodayStatus;
use App\Models\LevelContent;
use App\Models\RecommendedSong;
use App\Models\ReviewQuizResponse;
use App\Models\ReviewSession;
use App\Models\Student;
use App\Models\StudentFigureView;
use App\Models\StudentStreak;
use App\Services\Review\ReviewSessionService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ResetTodayReviewsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_todays_sessions_and_views_and_keeps_older_ones(): void
    {
        $this->travelToLocal('2026-10-30 10:00');

        $student = Student::factory()->create();
        $todaySession = $this->sessionStartedAt($student, now());
        $yesterdaySession = $this->sessionStartedAt($student, now()->subDay());
        $todayView = StudentFigureView::factory()->create(['student_id' => $student->id, 'viewed_at' => now()]);
        $yesterdayView = StudentFigureView::factory()->create(['student_id' => $student->id, 'viewed_at' => now()->subDay()]);

        $this->artisan('review:reset-today')
            ->expectsOutputToContain('Sesiones de repaso borradas: 1. Vistas de figuras borradas: 1.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('review_sessions', ['id' => $todaySession->id]);
        $this->assertDatabaseHas('review_sessions', ['id' => $yesterdaySession->id]);
        $this->assertDatabaseMissing('student_figure_views', ['id' => $todayView->id]);
        $this->assertDatabaseHas('student_figure_views', ['id' => $yesterdayView->id]);
    }

    public function test_deletes_the_figures_songs_and_quiz_answers_of_the_session(): void
    {
        $session = $this->sessionStartedAt(Student::factory()->create(), now());
        $session->figures()->attach(LevelContent::factory()->create(), ['selected_by_student' => true]);
        $session->songs()->attach(RecommendedSong::factory()->create());
        ReviewQuizResponse::factory()->create(['review_session_id' => $session->id]);

        $this->artisan('review:reset-today')->assertSuccessful();

        $this->assertDatabaseMissing('review_session_figures', ['review_session_id' => $session->id]);
        $this->assertDatabaseMissing('review_session_songs', ['review_session_id' => $session->id]);
        $this->assertDatabaseMissing('review_quiz_responses', ['review_session_id' => $session->id]);
    }

    public function test_student_option_leaves_other_students_untouched(): void
    {
        $student = Student::factory()->create();
        $otherStudent = Student::factory()->create();
        $session = $this->sessionStartedAt($student, now());
        $otherSession = $this->sessionStartedAt($otherStudent, now());
        $view = StudentFigureView::factory()->create(['student_id' => $student->id, 'viewed_at' => now()]);
        $otherView = StudentFigureView::factory()->create(['student_id' => $otherStudent->id, 'viewed_at' => now()]);

        $this->artisan('review:reset-today', ['--student' => $student->id])->assertSuccessful();

        $this->assertDatabaseMissing('review_sessions', ['id' => $session->id]);
        $this->assertDatabaseHas('review_sessions', ['id' => $otherSession->id]);
        $this->assertDatabaseMissing('student_figure_views', ['id' => $view->id]);
        $this->assertDatabaseHas('student_figure_views', ['id' => $otherView->id]);
    }

    public function test_student_without_reviews_today_reports_nothing_deleted(): void
    {
        $session = $this->sessionStartedAt(Student::factory()->create(), now());

        $this->artisan('review:reset-today', ['--student' => $session->student_id + 1])
            ->expectsOutputToContain('Sesiones de repaso borradas: 0. Vistas de figuras borradas: 0.')
            ->assertSuccessful();

        $this->assertDatabaseHas('review_sessions', ['id' => $session->id]);
    }

    public function test_unlocks_todays_review(): void
    {
        $student = Student::factory()->create();
        $service = app(ReviewSessionService::class);
        ReviewSession::factory()->completed()->create([
            'student_id' => $student->id,
            'started_at' => now()->subHour(),
            'expires_at' => now()->subMinutes(55),
        ]);

        $this->assertSame(ReviewTodayStatus::Locked, $service->todayFor($student)['status']);

        $this->artisan('review:reset-today')->assertSuccessful();

        $this->assertSame(ReviewTodayStatus::Available, $service->todayFor($student)['status']);
    }

    public function test_keeps_a_session_from_last_night_that_is_today_in_utc(): void
    {
        $this->travelToLocal('2026-10-30 10:00');

        $session = $this->sessionStartedAt(
            Student::factory()->create(),
            CarbonImmutable::parse('2026-10-29 23:30', 'America/Caracas')->utc(),
        );

        $this->artisan('review:reset-today')->assertSuccessful();

        $this->assertDatabaseHas('review_sessions', ['id' => $session->id]);
    }

    public function test_deletes_a_session_from_this_morning_after_the_utc_day_changed(): void
    {
        $this->travelToLocal('2026-10-30 21:30');

        $session = $this->sessionStartedAt(
            Student::factory()->create(),
            CarbonImmutable::parse('2026-10-30 10:00', 'America/Caracas')->utc(),
        );

        $this->artisan('review:reset-today')->assertSuccessful();

        $this->assertDatabaseMissing('review_sessions', ['id' => $session->id]);
    }

    #[DataProvider('protectedEnvironments')]
    public function test_refuses_to_run_outside_local_and_testing(string $environment): void
    {
        $student = Student::factory()->create();
        $session = $this->sessionStartedAt($student, now());
        $view = StudentFigureView::factory()->create(['student_id' => $student->id, 'viewed_at' => now()]);

        $this->app->detectEnvironment(fn (): string => $environment);

        $this->artisan('review:reset-today')
            ->expectsOutputToContain('Este comando solo se puede usar en local.')
            ->assertFailed();

        $this->assertDatabaseHas('review_sessions', ['id' => $session->id]);
        $this->assertDatabaseHas('student_figure_views', ['id' => $view->id]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function protectedEnvironments(): array
    {
        return [
            'staging' => ['staging'],
            'production' => ['production'],
        ];
    }

    public function test_does_not_touch_the_streak(): void
    {
        $this->freezeSecond();

        $student = Student::factory()->create();
        $this->sessionStartedAt($student, now());
        $streak = StudentStreak::factory()->create([
            'student_id' => $student->id,
            'current_streak' => 3,
            'last_review_at' => now(),
        ]);

        $this->artisan('review:reset-today')->assertSuccessful();

        $this->assertDatabaseHas('student_streaks', [
            'id' => $streak->id,
            'current_streak' => 3,
            'last_review_at' => now()->toDateTimeString(),
        ]);
    }

    private function sessionStartedAt(Student $student, CarbonInterface $startedAt): ReviewSession
    {
        return ReviewSession::factory()->create([
            'student_id' => $student->id,
            'started_at' => $startedAt,
            'expires_at' => $startedAt->addMinutes(5),
        ]);
    }

    private function travelToLocal(string $localTime): void
    {
        $this->travelTo(CarbonImmutable::parse($localTime, 'America/Caracas')->utc());
    }
}
