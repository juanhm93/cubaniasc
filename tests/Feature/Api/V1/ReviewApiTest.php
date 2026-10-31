<?php

namespace Tests\Feature\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Api\V1\Auth\VerifyLoginCodeController;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\LevelContent;
use App\Models\QuizItem;
use App\Models\QuizOption;
use App\Models\RecommendedSong;
use App\Models\ReviewQuizResponse;
use App\Models\ReviewSession;
use App\Models\Student;
use App\Models\StudentFigureView;
use App\Models\StudentStreak;
use App\Services\Review\ReviewSessionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_today_is_available_when_the_student_has_not_reviewed_yet(): void
    {
        [$student, $level] = $this->createEnrolledStudent(['review_duration_seconds' => 600]);

        $this->actingAsAppStudent($student)
            ->getJson(route('v1.review.today'))
            ->assertOk()
            ->assertJsonPath('data.status', 'available')
            ->assertJsonPath('data.session', null)
            ->assertJsonPath('data.level.id', $level->id)
            ->assertJsonPath('data.level.review_duration_seconds', 600)
            ->assertJsonPath('data.streak.current_streak', 0)
            ->assertJsonCount(7, 'data.streak.recent_days')
            ->assertJsonStructure(['data' => ['streak' => ['last_review_at', 'next_day_starts_at']]]);
    }

    public function test_today_returns_the_session_in_progress(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $session = $this->createActiveSession($student, $level);

        $this->actingAsAppStudent($student)
            ->getJson(route('v1.review.today'))
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.session.id', $session->id)
            ->assertJsonPath('data.session.next_step', 'select_figures')
            ->assertJsonPath('data.session.quiz_answered_count', 0)
            ->assertJsonPath('data.session.selected_figures', []);
    }

    public function test_today_is_locked_once_todays_review_was_used(): void
    {
        $this->travelToLocal('2026-10-30 10:00');

        [$student, $level] = $this->createEnrolledStudent();
        ReviewSession::factory()->completed()->create([
            'student_id' => $student->id,
            'level_id' => $level->id,
            'started_at' => now()->subMinutes(10),
            'expires_at' => now()->subMinutes(5),
        ]);

        $this->actingAsAppStudent($student)
            ->getJson(route('v1.review.today'))
            ->assertOk()
            ->assertJsonPath('data.status', 'locked')
            ->assertJsonPath('data.session', null)
            ->assertJsonPath('data.streak.next_day_starts_at', ReviewSessionService::nextDayStartsAt()->toIso8601String());
    }

    public function test_today_closes_an_expired_session_and_locks_the_day(): void
    {
        $this->travelToLocal('2026-10-30 10:00');

        [$student, $level] = $this->createEnrolledStudent();
        $expired = ReviewSession::factory()->expired()->create([
            'student_id' => $student->id,
            'level_id' => $level->id,
        ]);

        $this->actingAsAppStudent($student)
            ->getJson(route('v1.review.today'))
            ->assertOk()
            ->assertJsonPath('data.status', 'locked');

        $this->assertTrue($expired->fresh()->completed);
        $this->assertNull($expired->fresh()->completed_at);
    }

    public function test_today_requires_an_active_enrollment(): void
    {
        $student = Student::factory()->create();

        $this->actingAsAppStudent($student)
            ->getJson(route('v1.review.today'))
            ->assertNotFound()
            ->assertJsonPath('message', 'No tienes una inscripción activa en un curso de Cubanía.');
    }

    public function test_review_endpoints_reject_guests_and_review_panel_tokens(): void
    {
        [$student] = $this->createEnrolledStudent();
        $reviewPanelToken = $student->createToken('review-panel')->plainTextToken;

        $this->getJson(route('v1.review.today'))->assertUnauthorized();
        $this->postJson(route('v1.review.sessions.store'))->assertUnauthorized();

        $this->withToken($reviewPanelToken)->getJson(route('v1.review.today'))->assertForbidden();
        $this->withToken($reviewPanelToken)->postJson(route('v1.review.sessions.store'))->assertForbidden();
    }

    public function test_starting_creates_the_session_and_starting_again_resumes_it(): void
    {
        [$student, $level] = $this->createEnrolledStudent(['review_duration_seconds' => 420]);
        $client = $this->actingAsAppStudent($student);

        $created = $client->postJson(route('v1.review.sessions.store'))
            ->assertCreated()
            ->assertJsonPath('data.level_id', $level->id)
            ->assertJsonPath('data.level.id', $level->id)
            ->assertJsonPath('data.duration_seconds', 420)
            ->assertJsonPath('data.expired', false)
            ->assertJsonPath('data.completed_at', null)
            ->assertJsonPath('data.next_step', 'select_figures')
            ->assertJsonPath('data.quiz_max', ReviewSessionService::MAX_QUIZ_QUESTIONS);

        $this->assertGreaterThanOrEqual(419, $created->json('data.remaining_seconds'));

        $client->postJson(route('v1.review.sessions.store'))
            ->assertOk()
            ->assertJsonPath('data.id', $created->json('data.id'));

        $this->assertSame(1, ReviewSession::query()->where('student_id', $student->id)->count());
    }

    public function test_starting_after_using_todays_review_is_rejected(): void
    {
        $this->travelToLocal('2026-10-30 10:00');

        [$student, $level] = $this->createEnrolledStudent();
        ReviewSession::factory()->completed()->create([
            'student_id' => $student->id,
            'level_id' => $level->id,
            'started_at' => now()->subMinutes(10),
            'expires_at' => now()->subMinutes(5),
        ]);

        $this->actingAsAppStudent($student)
            ->postJson(route('v1.review.sessions.store'))
            ->assertConflict()
            ->assertJsonPath('locked', true);
    }

    public function test_a_student_completes_the_whole_review_flow(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        LevelContent::factory()->count(4)->for($level)->create();
        RecommendedSong::factory()->count(3)->create()->each(fn (RecommendedSong $song) => $song->levels()->attach($level));
        $quizItem = QuizItem::factory()->for($level)->create();
        $correct = QuizOption::factory()->for($quizItem)->correct()->create();
        $wrong = QuizOption::factory()->for($quizItem)->create();
        $client = $this->actingAsAppStudent($student);

        $sessionId = $client->postJson(route('v1.review.sessions.store'))->assertCreated()->json('data.id');

        $options = $client->getJson(route('v1.review.sessions.figure-options', $sessionId))
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonStructure(['data' => [['id', 'level_id', 'level_name', 'name', 'description', 'video_url', 'youtube_video_id']]])
            ->json('data');
        $chosenIds = [$options[0]['id'], $options[1]['id']];

        $client->postJson(route('v1.review.sessions.figures.store', $sessionId), ['level_content_ids' => $chosenIds])
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $client->getJson(route('v1.review.sessions.show', $sessionId))
            ->assertOk()
            ->assertJsonPath('data.next_step', 'review_figures')
            ->assertJsonCount(2, 'data.selected_figures');

        $client->postJson(route('v1.review.sessions.figures.view', ['session' => $sessionId, 'figure' => $chosenIds[0]]))
            ->assertNoContent();
        $client->postJson(route('v1.review.sessions.figures.view', ['session' => $sessionId, 'figure' => $options[2]['id']]))
            ->assertNotFound();
        $this->assertSame(1, StudentFigureView::query()->where('student_id', $student->id)->count());

        $question = $client->getJson(route('v1.review.sessions.quiz.next', $sessionId))
            ->assertOk()
            ->assertJsonPath('data.id', $quizItem->id)
            ->assertJsonCount(2, 'data.options')
            ->json('data');
        $this->assertSame(['id', 'description'], array_keys($question['options'][0]));

        $client->postJson(route('v1.review.sessions.quiz.answer', ['session' => $sessionId, 'item' => $quizItem->id]), [
            'quiz_option_id' => $wrong->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_correct', false)
            ->assertJsonPath('data.correct_option_id', $correct->id)
            ->assertJsonPath('data.answered_count', 1)
            ->assertJsonPath('data.quiz_max', ReviewSessionService::MAX_QUIZ_QUESTIONS);

        $client->getJson(route('v1.review.sessions.show', $sessionId))->assertJsonPath('data.next_step', 'quiz');
        $client->getJson(route('v1.review.sessions.quiz.next', $sessionId))->assertOk()->assertJsonPath('data', null);

        $songs = $client->getJson(route('v1.review.sessions.songs', $sessionId))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => [['id', 'title', 'artist', 'audio_or_link_url']]])
            ->json('data');
        $client->getJson(route('v1.review.sessions.songs', $sessionId))->assertJsonPath('data', $songs);
        $client->getJson(route('v1.review.sessions.show', $sessionId))->assertJsonPath('data.next_step', 'songs');

        $client->postJson(route('v1.review.sessions.complete', $sessionId))
            ->assertOk()
            ->assertJsonPath('data.session.next_step', 'completed')
            ->assertJsonPath('data.streak.current_streak', 1);

        $client->getJson(route('v1.review.today'))
            ->assertJsonPath('data.status', 'locked')
            ->assertJsonPath('data.streak.current_streak', 1);
    }

    public function test_completing_twice_does_not_grow_the_streak_again(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $session = $this->createActiveSession($student, $level);
        $client = $this->actingAsAppStudent($student);

        $client->postJson(route('v1.review.sessions.complete', $session))->assertJsonPath('data.streak.current_streak', 1);
        $client->postJson(route('v1.review.sessions.complete', $session))->assertJsonPath('data.streak.current_streak', 1);

        $this->assertSame(1, StudentStreak::query()->where('student_id', $student->id)->value('current_streak'));
    }

    public function test_figure_selection_must_match_the_offered_options(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        LevelContent::factory()->count(4)->for($level)->create();
        $notOffered = LevelContent::factory()->for($level)->create();
        $session = $this->createActiveSession($student, $level);
        $client = $this->actingAsAppStudent($student);

        $offeredIds = array_column($client->getJson(route('v1.review.sessions.figure-options', $session))->json('data'), 'id');
        $offeredIds = array_values(array_diff($offeredIds, [$notOffered->id]));

        $client->postJson(route('v1.review.sessions.figures.store', $session), ['level_content_ids' => [$offeredIds[0]]])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Debes elegir exactamente 2 figuras.');

        $client->postJson(route('v1.review.sessions.figures.store', $session), [
            'level_content_ids' => [$offeredIds[0], LevelContent::factory()->create()->id],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Alguna de las figuras elegidas no está disponible en este repaso.');

        $client->postJson(route('v1.review.sessions.figures.store', $session), ['level_content_ids' => [1, 2, 3]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('level_content_ids');
    }

    public function test_figure_options_keep_the_same_figures_on_reload(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        LevelContent::factory()->count(8)->for($level)->create();
        $session = $this->createActiveSession($student, $level);
        $client = $this->actingAsAppStudent($student);

        $first = $client->getJson(route('v1.review.sessions.figure-options', $session))->json('data');
        $second = $client->getJson(route('v1.review.sessions.figure-options', $session))->json('data');

        $this->assertSame(array_column($first, 'id'), array_column($second, 'id'));
    }

    public function test_figures_expose_the_youtube_video_id(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        LevelContent::factory()->for($level)->create(['video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);
        $session = $this->createActiveSession($student, $level);

        $this->actingAsAppStudent($student)
            ->getJson(route('v1.review.sessions.figure-options', $session))
            ->assertOk()
            ->assertJsonPath('data.0.youtube_video_id', 'dQw4w9WgXcQ');
    }

    public function test_answering_with_an_option_of_another_question_is_rejected(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $session = $this->createActiveSession($student, $level);
        $quizItem = QuizItem::factory()->for($level)->create();
        QuizOption::factory()->for($quizItem)->correct()->create();
        $foreignOption = QuizOption::factory()->for(QuizItem::factory()->for($level))->create();

        $this->actingAsAppStudent($student)
            ->postJson(route('v1.review.sessions.quiz.answer', ['session' => $session, 'item' => $quizItem]), [
                'quiz_option_id' => $foreignOption->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quiz_option_id');

        $this->assertDatabaseCount('review_quiz_responses', 0);
    }

    public function test_a_question_can_only_be_answered_once(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $session = $this->createActiveSession($student, $level);
        $quizItem = QuizItem::factory()->for($level)->create();
        $option = QuizOption::factory()->for($quizItem)->correct()->create();
        $client = $this->actingAsAppStudent($student);
        $url = route('v1.review.sessions.quiz.answer', ['session' => $session, 'item' => $quizItem]);

        $client->postJson($url, ['quiz_option_id' => $option->id])->assertOk()->assertJsonPath('data.is_correct', true);
        $client->postJson($url, ['quiz_option_id' => $option->id])
            ->assertConflict()
            ->assertJsonPath('message', 'Ya respondiste esta pregunta.');
    }

    public function test_questions_outside_the_session_pool_are_not_found(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $session = $this->createActiveSession($student, $level);
        $otherLevelItem = QuizItem::factory()->create();
        $option = QuizOption::factory()->for($otherLevelItem)->correct()->create();

        $this->actingAsAppStudent($student)
            ->postJson(route('v1.review.sessions.quiz.answer', ['session' => $session, 'item' => $otherLevelItem]), [
                'quiz_option_id' => $option->id,
            ])
            ->assertNotFound();
    }

    public function test_an_expired_session_rejects_timed_steps_but_can_still_be_completed(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $figure = LevelContent::factory()->for($level)->create();
        RecommendedSong::factory()->create()->levels()->attach($level);
        $session = ReviewSession::factory()->expired()->create([
            'student_id' => $student->id,
            'level_id' => $level->id,
        ]);
        $session->figures()->attach($figure->id, ['selected_by_student' => true]);
        $client = $this->actingAsAppStudent($student);

        $client->getJson(route('v1.review.sessions.show', $session))
            ->assertOk()
            ->assertJsonPath('data.expired', true)
            ->assertJsonPath('data.remaining_seconds', 0)
            ->assertJsonPath('data.next_step', 'expired');

        $client->getJson(route('v1.review.sessions.figure-options', $session))
            ->assertStatus(410)
            ->assertJsonPath('message', 'Se acabó el tiempo de este repaso.');
        $client->getJson(route('v1.review.sessions.quiz.next', $session))->assertStatus(410);
        $client->postJson(route('v1.review.sessions.figures.view', ['session' => $session, 'figure' => $figure]))
            ->assertStatus(410);

        $client->getJson(route('v1.review.sessions.songs', $session))->assertOk()->assertJsonCount(1, 'data');
        $client->getJson(route('v1.review.sessions.show', $session))->assertJsonPath('data.next_step', 'songs');
        $client->postJson(route('v1.review.sessions.complete', $session))
            ->assertOk()
            ->assertJsonPath('data.session.next_step', 'completed');
    }

    public function test_a_session_cannot_be_completed_before_choosing_figures(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        LevelContent::factory()->count(4)->for($level)->create();
        $session = $this->createActiveSession($student, $level);
        $client = $this->actingAsAppStudent($student);

        $client->getJson(route('v1.review.sessions.figure-options', $session))->assertOk();

        $client->postJson(route('v1.review.sessions.complete', $session))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Elige y repasa tus figuras antes de terminar el repaso.');
    }

    public function test_next_step_moves_to_the_quiz_when_the_figures_were_skipped(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $session = $this->createActiveSession($student, $level);
        $quizItem = QuizItem::factory()->for($level)->create();
        $option = QuizOption::factory()->for($quizItem)->create();
        ReviewQuizResponse::factory()->create([
            'review_session_id' => $session->id,
            'quiz_item_id' => $quizItem->id,
            'quiz_option_id' => $option->id,
        ]);

        $this->actingAsAppStudent($student)
            ->getJson(route('v1.review.sessions.show', $session))
            ->assertJsonPath('data.next_step', 'quiz')
            ->assertJsonPath('data.quiz_answered_count', 1);
    }

    public function test_next_step_is_songs_once_the_question_limit_is_reached(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $session = $this->createActiveSession($student, $level);

        QuizItem::factory()->count(ReviewSessionService::MAX_QUIZ_QUESTIONS)->for($level)->create()
            ->each(function (QuizItem $item) use ($session): void {
                $option = QuizOption::factory()->for($item)->correct()->create();
                ReviewQuizResponse::factory()->create([
                    'review_session_id' => $session->id,
                    'quiz_item_id' => $item->id,
                    'quiz_option_id' => $option->id,
                ]);
            });

        $this->actingAsAppStudent($student)
            ->getJson(route('v1.review.sessions.show', $session))
            ->assertJsonPath('data.next_step', 'songs');
    }

    public function test_a_student_cannot_use_another_students_session(): void
    {
        [$student] = $this->createEnrolledStudent();
        [$otherStudent, $otherLevel] = $this->createEnrolledStudent();
        $session = $this->createActiveSession($otherStudent, $otherLevel);
        $client = $this->actingAsAppStudent($student);

        $client->getJson(route('v1.review.sessions.show', $session))->assertForbidden();
        $client->getJson(route('v1.review.sessions.figure-options', $session))->assertForbidden();
        $client->getJson(route('v1.review.sessions.quiz.next', $session))->assertForbidden();
        $client->getJson(route('v1.review.sessions.songs', $session))->assertForbidden();
        $client->postJson(route('v1.review.sessions.complete', $session))->assertForbidden();
    }

    private function travelToLocal(string $localTime): void
    {
        $this->travelTo(CarbonImmutable::parse($localTime, 'America/Caracas')->utc());
    }

    private function actingAsAppStudent(Student $student): static
    {
        $token = $student->createToken(VerifyLoginCodeController::TOKEN_PREFIX.'Pixel 8', ['app'])->plainTextToken;

        return $this->withToken($token);
    }

    private function createActiveSession(Student $student, Level $level): ReviewSession
    {
        return ReviewSession::factory()->create([
            'student_id' => $student->id,
            'level_id' => $level->id,
            'started_at' => now()->subMinute(),
            'expires_at' => now()->addMinutes(10),
        ]);
    }

    /**
     * @param  array<string, mixed>  $levelAttributes
     * @return array{0: Student, 1: Level}
     */
    private function createEnrolledStudent(array $levelAttributes = []): array
    {
        $level = Level::factory()->create($levelAttributes);
        $course = Course::factory()->create([
            'level_id' => $level->id,
            'is_active' => true,
        ]);
        $student = Student::factory()->create();

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Active,
            'enrolled_at' => now()->subMonth(),
        ]);

        return [$student, $level];
    }
}
