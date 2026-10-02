<?php

namespace Tests\Feature\Review;

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\LevelContent;
use App\Models\QuizItem;
use App\Models\QuizOption;
use App\Models\ReviewSession;
use App\Models\Student;
use App\Services\Review\StreakService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewPanelIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cubania.timezone' => 'America/Caracas']);
    }

    public function test_daily_limit_uses_the_academy_local_day(): void
    {
        [$student] = $this->createEnrolledStudent();
        Sanctum::actingAs($student);

        $this->travelToLocal('2026-09-24 21:00');
        $this->postJson(route('review.sessions.store'))->assertCreated();

        $this->travelToLocal('2026-09-25 19:00');
        $this->postJson(route('review.sessions.store'))->assertCreated();
    }

    public function test_daily_limit_blocks_a_second_session_on_the_same_local_day_across_utc_midnight(): void
    {
        [$student] = $this->createEnrolledStudent();
        Sanctum::actingAs($student);

        $this->travelToLocal('2026-09-24 10:00');
        $this->postJson(route('review.sessions.store'))->assertCreated();

        $this->travelToLocal('2026-09-24 21:00');
        $this->postJson(route('review.sessions.store'))->assertConflict();
    }

    public function test_streak_counts_consecutive_local_days(): void
    {
        [$student] = $this->createEnrolledStudent();
        $service = app(StreakService::class);

        $this->travelToLocal('2026-09-24 21:30');
        $service->recordCompletion($student);

        $this->travelToLocal('2026-09-25 18:00');
        $streak = $service->recordCompletion($student);

        $this->assertSame(2, $streak->current_streak);
    }

    public function test_figure_options_are_persisted_and_stable_between_requests(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        LevelContent::factory()->count(8)->for($level)->create();
        $session = $this->createActiveSession($student, $level);
        Sanctum::actingAs($student);

        $first = $this->getJson(route('review.sessions.figure-options', $session))
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->json('data.*.id');

        $second = $this->getJson(route('review.sessions.figure-options', $session))->json('data.*.id');

        $this->assertSame($first, $second);
        $this->assertDatabaseCount('review_session_figures', 4);
    }

    public function test_selection_must_come_from_the_offered_options_and_only_once(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        LevelContent::factory()->count(8)->for($level)->create();
        $session = $this->createActiveSession($student, $level);
        Sanctum::actingAs($student);

        $offeredIds = $this->getJson(route('review.sessions.figure-options', $session))->json('data.*.id');
        $notOfferedId = LevelContent::query()->whereNotIn('id', $offeredIds)->value('id');

        $this->postJson(route('review.sessions.figures.store', $session), [
            'level_content_ids' => [$offeredIds[0], $notOfferedId],
        ])->assertUnprocessable();

        $this->postJson(route('review.sessions.figures.store', $session), [
            'level_content_ids' => [$offeredIds[0], $offeredIds[1]],
        ])->assertOk()->assertJsonCount(2, 'data');

        $this->postJson(route('review.sessions.figures.store', $session), [
            'level_content_ids' => [$offeredIds[2], $offeredIds[3]],
        ])->assertUnprocessable()->assertJsonPath('message', 'Ya elegiste las figuras de este repaso.');

        $this->assertSame(2, $session->selectedFigures()->count());
    }

    public function test_session_resource_exposes_progress_to_resume(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $session = $this->createActiveSession($student, $level);
        $figures = LevelContent::factory()->count(2)->for($level)->create();
        $session->figures()->attach($figures->pluck('id')->all(), ['selected_by_student' => true]);
        Sanctum::actingAs($student);

        $this->getJson(route('review.sessions.current'))
            ->assertOk()
            ->assertJsonCount(2, 'data.selected_figures')
            ->assertJsonPath('data.quiz_answered_count', 0)
            ->assertJsonPath('data.quiz_max', 5)
            ->assertJsonPath('data.duration_seconds', 1800);
    }

    public function test_viewing_a_figure_that_was_not_selected_is_rejected(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $session = $this->createActiveSession($student, $level);
        $selected = LevelContent::factory()->for($level)->create();
        $other = LevelContent::factory()->for($level)->create();
        $session->figures()->attach($selected->id, ['selected_by_student' => true]);
        Sanctum::actingAs($student);

        $this->postJson(route('review.sessions.figures.view', [$session, $other]))->assertNotFound();
        $this->postJson(route('review.sessions.figures.view', [$session, $selected]))->assertOk();
    }

    public function test_quiz_answer_returns_correct_option_and_rejects_duplicates(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $session = $this->createActiveSession($student, $level);
        $item = QuizItem::factory()->create(['level_id' => $level->id]);
        $wrong = QuizOption::factory()->create(['quiz_item_id' => $item->id]);
        $correct = QuizOption::factory()->correct()->create(['quiz_item_id' => $item->id]);
        Sanctum::actingAs($student);

        $this->postJson(route('review.sessions.quiz.answer', [$session, $item]), [
            'quiz_option_id' => $wrong->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_correct', false)
            ->assertJsonPath('data.correct_option_id', $correct->id);

        $this->postJson(route('review.sessions.quiz.answer', [$session, $item]), [
            'quiz_option_id' => $correct->id,
        ])->assertConflict();

        $this->assertDatabaseCount('review_quiz_responses', 1);
    }

    public function test_quiz_answer_rejects_an_option_of_another_item(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $session = $this->createActiveSession($student, $level);
        $item = QuizItem::factory()->create(['level_id' => $level->id]);
        $foreignOption = QuizOption::factory()->correct()->create([
            'quiz_item_id' => QuizItem::factory()->create(['level_id' => $level->id])->id,
        ]);
        Sanctum::actingAs($student);

        $this->postJson(route('review.sessions.quiz.answer', [$session, $item]), [
            'quiz_option_id' => $foreignOption->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('quiz_option_id');
    }

    public function test_complete_requires_selected_figures_when_figures_were_offered(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $session = $this->createActiveSession($student, $level);
        $session->figures()->attach(
            LevelContent::factory()->count(2)->for($level)->create()->pluck('id')->all(),
            ['selected_by_student' => false],
        );
        Sanctum::actingAs($student);

        $this->postJson(route('review.sessions.complete', $session))->assertUnprocessable();

        $this->assertNull($session->fresh()->completed_at);
    }

    public function test_complete_is_allowed_when_the_catalog_had_no_figures_to_offer(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $session = $this->createActiveSession($student, $level);
        Sanctum::actingAs($student);

        $this->getJson(route('review.sessions.figure-options', $session))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->postJson(route('review.sessions.complete', $session))
            ->assertOk()
            ->assertJsonPath('data.streak.current_streak', 1);
    }

    public function test_a_single_offered_figure_can_be_selected_alone(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $onlyFigure = LevelContent::factory()->for($level)->create();
        $session = $this->createActiveSession($student, $level);
        Sanctum::actingAs($student);

        $this->getJson(route('review.sessions.figure-options', $session))->assertJsonCount(1, 'data');

        $this->postJson(route('review.sessions.figures.store', $session), [
            'level_content_ids' => [$onlyFigure->id],
        ])->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_one_figure_is_rejected_when_more_were_offered(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        LevelContent::factory()->count(4)->for($level)->create();
        $session = $this->createActiveSession($student, $level);
        Sanctum::actingAs($student);

        $offeredIds = $this->getJson(route('review.sessions.figure-options', $session))->json('data.*.id');

        $this->postJson(route('review.sessions.figures.store', $session), [
            'level_content_ids' => [$offeredIds[0]],
        ])->assertUnprocessable()->assertJsonPath('message', 'Debes elegir exactamente 2 figuras.');
    }

    public function test_completing_twice_does_not_change_the_streak_or_completion_time(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $session = $this->createActiveSession($student, $level);
        $session->figures()->attach(
            LevelContent::factory()->count(2)->for($level)->create()->pluck('id')->all(),
            ['selected_by_student' => true],
        );
        Sanctum::actingAs($student);

        $this->postJson(route('review.sessions.complete', $session))
            ->assertOk()
            ->assertJsonPath('data.streak.current_streak', 1);
        $completedAt = $session->fresh()->completed_at;

        $this->travel(5)->minutes();

        $this->postJson(route('review.sessions.complete', $session))
            ->assertOk()
            ->assertJsonPath('data.streak.current_streak', 1);

        $this->assertTrue($completedAt->equalTo($session->fresh()->completed_at));
    }

    public function test_streak_endpoint_returns_recent_days(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        ReviewSession::factory()->completed()->create([
            'student_id' => $student->id,
            'level_id' => $level->id,
            'started_at' => now(),
            'expires_at' => now()->addMinutes(30),
            'completed_at' => now(),
        ]);
        Sanctum::actingAs($student);

        $response = $this->getJson(route('review.streak'))
            ->assertOk()
            ->assertJsonCount(7, 'data.recent_days');

        $this->assertTrue($response->json('data.recent_days.6.completed'));
        $this->assertTrue(CarbonImmutable::parse($response->json('data.next_day_starts_at'))->isFuture());
        $this->assertFalse($response->json('data.recent_days.5.completed'));
    }

    public function test_identify_replaces_previous_tokens_and_sets_expiration(): void
    {
        [$student] = $this->createEnrolledStudent();
        $student->createToken('review-panel');

        $this->postJson(route('review.identify'), ['email' => $student->email])->assertOk();

        $tokens = $student->tokens()->get();

        $this->assertCount(1, $tokens);
        $this->assertNotNull($tokens->first()->expires_at);
    }

    public function test_identify_uses_the_same_message_for_unknown_and_not_enrolled_students(): void
    {
        $notEnrolled = Student::factory()->create();

        $unknownMessage = $this->postJson(route('review.identify'), ['dni' => 'no-existe'])
            ->assertNotFound()
            ->json('message');

        $notEnrolledMessage = $this->postJson(route('review.identify'), ['email' => $notEnrolled->email])
            ->assertNotFound()
            ->json('message');

        $this->assertSame($unknownMessage, $notEnrolledMessage);
    }

    public function test_identify_is_rate_limited_per_ip_even_when_changing_identifier(): void
    {
        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $this->postJson(route('review.identify'), ['dni' => "V-{$attempt}"])->assertNotFound();
        }

        $this->postJson(route('review.identify'), ['dni' => 'V-21'])->assertTooManyRequests();
    }

    /**
     * Travels to a wall-clock time in Caracas, pinned as a UTC instant.
     */
    private function travelToLocal(string $localTime): void
    {
        $this->travelTo(CarbonImmutable::parse($localTime, 'America/Caracas')->utc());
    }

    private function createActiveSession(Student $student, Level $level): ReviewSession
    {
        return ReviewSession::factory()->create([
            'student_id' => $student->id,
            'level_id' => $level->id,
            'started_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);
    }

    /**
     * @return array{0: Student, 1: Level}
     */
    private function createEnrolledStudent(): array
    {
        $level = Level::factory()->create(['review_duration_seconds' => 1800]);
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
