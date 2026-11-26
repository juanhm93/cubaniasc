<?php

namespace Tests\Feature\Review;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Api\Review\VerifyReviewLoginCodeController;
use App\Http\Controllers\Api\V1\Auth\VerifyLoginCodeController;
use App\Http\Middleware\EnsureAppStudentToken;
use App\Http\Middleware\EnsureReviewStudentToken;
use App\Mail\StudentLoginCodeMail;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\LevelContent;
use App\Models\QuizItem;
use App\Models\QuizOption;
use App\Models\RecommendedSong;
use App\Models\ReviewSession;
use App\Models\Student;
use App\Models\StudentLoginCode;
use App\Models\StudentStreak;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewPanelApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_code_emails_a_one_time_code_to_an_enrolled_student(): void
    {
        Mail::fake();
        [$student] = $this->createEnrolledStudent();

        $this->postJson(route('review.auth.request-code'), ['email' => $student->email])
            ->assertStatus(202)
            ->assertJsonMissingPath('data.token');

        Mail::assertSent(StudentLoginCodeMail::class, fn (StudentLoginCodeMail $mail): bool => $mail->hasTo($student->email));
        $this->assertSame(0, $student->tokens()->count());
    }

    public function test_verify_code_returns_a_review_token_without_personal_data(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        StudentLoginCode::factory()->for($student)->withCode('482913')->create();

        $response = $this->postJson(route('review.auth.verify-code'), [
            'email' => $student->email,
            'code' => '482913',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'student' => ['id', 'name'],
                    'level' => ['id', 'name', 'slug', 'review_duration_seconds'],
                ],
            ])
            ->assertJsonMissingPath('data.student.email')
            ->assertJsonMissingPath('data.student.dni')
            ->assertJsonPath('data.student.id', $student->id)
            ->assertJsonPath('data.level.id', $level->id);

        $token = $student->tokens()->sole();
        $this->assertSame(VerifyReviewLoginCodeController::TOKEN_NAME, $token->name);
        $this->assertSame([EnsureReviewStudentToken::ABILITY], $token->abilities);
        $this->assertSame(0, $student->loginCodes()->pending()->count());
    }

    public function test_verify_code_without_a_valid_code_does_not_issue_a_token(): void
    {
        [$student] = $this->createEnrolledStudent();
        StudentLoginCode::factory()->for($student)->withCode('482913')->create();

        $this->postJson(route('review.auth.verify-code'), ['email' => $student->email])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->postJson(route('review.auth.verify-code'), ['email' => $student->email, 'code' => '111111'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->assertSame(0, $student->tokens()->count());
    }

    public function test_verify_code_rejects_unenrolled_students(): void
    {
        $student = Student::factory()->create(['email' => 'solo@example.com']);
        StudentLoginCode::factory()->for($student)->withCode('482913')->create();

        $this->postJson(route('review.auth.verify-code'), ['email' => 'solo@example.com', 'code' => '482913'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->assertSame(0, $student->tokens()->count());
    }

    public function test_review_routes_reject_tokens_without_the_review_ability(): void
    {
        [$student] = $this->createEnrolledStudent();

        $tokens = [
            'legacy wildcard token' => $student->createToken(VerifyReviewLoginCodeController::TOKEN_NAME)->plainTextToken,
            'mobile app token' => $student->createToken(VerifyLoginCodeController::TOKEN_PREFIX.'Pixel 8', [EnsureAppStudentToken::ABILITY])->plainTextToken,
        ];

        foreach ($tokens as $plainTextToken) {
            Auth::forgetGuards();

            $this->withToken($plainTextToken)
                ->getJson(route('review.me'))
                ->assertForbidden();
        }

    }

    public function test_authenticated_student_can_create_review_session(): void
    {
        $this->freezeSecond();

        [$student, $level] = $this->createEnrolledStudent(['review_duration_seconds' => 420]);
        Sanctum::actingAs($student, ['review']);

        $response = $this->postJson(route('review.sessions.store'));

        $response->assertCreated()
            ->assertJsonPath('data.level_id', $level->id)
            ->assertJsonPath('data.completed', false)
            ->assertJsonPath('data.remaining_seconds', 420);
    }

    public function test_student_can_view_own_session_state(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $session = ReviewSession::factory()->create([
            'student_id' => $student->id,
            'level_id' => $level->id,
            'expires_at' => now()->addMinutes(5),
        ]);

        Sanctum::actingAs($student, ['review']);

        $this->getJson(route('review.sessions.show', $session))
            ->assertOk()
            ->assertJsonPath('data.id', $session->id)
            ->assertJsonPath('data.expired', false);
    }

    public function test_student_cannot_access_another_students_session(): void
    {
        [$student] = $this->createEnrolledStudent();
        [$otherStudent, $otherLevel] = $this->createEnrolledStudent();
        $session = ReviewSession::factory()->create([
            'student_id' => $otherStudent->id,
            'level_id' => $otherLevel->id,
        ]);

        Sanctum::actingAs($student, ['review']);

        $this->getJson(route('review.sessions.show', $session))->assertForbidden();
    }

    public function test_figure_options_returns_up_to_four_level_figures(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        LevelContent::factory()->count(4)->for($level)->create();
        $session = ReviewSession::factory()->create([
            'student_id' => $student->id,
            'level_id' => $level->id,
            'expires_at' => now()->addMinutes(5),
        ]);

        Sanctum::actingAs($student, ['review']);

        $this->getJson(route('review.sessions.figure-options', $session))
            ->assertOk()
            ->assertJsonCount(4, 'data');
    }

    public function test_store_figures_rejects_selection_outside_options(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $figures = LevelContent::factory()->count(4)->for($level)->create();
        $outside = LevelContent::factory()->for(Level::factory()->create())->create();
        $session = ReviewSession::factory()->create([
            'student_id' => $student->id,
            'level_id' => $level->id,
            'expires_at' => now()->addMinutes(5),
        ]);

        Sanctum::actingAs($student, ['review']);

        $this->postJson(route('review.sessions.figures.store', $session), [
            'level_content_ids' => [$figures[0]->id, $outside->id],
        ])->assertUnprocessable();
    }

    public function test_store_figures_persists_two_selected_figures(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $figures = LevelContent::factory()->count(4)->for($level)->create();
        $session = ReviewSession::factory()->create([
            'student_id' => $student->id,
            'level_id' => $level->id,
            'expires_at' => now()->addMinutes(5),
        ]);

        Sanctum::actingAs($student, ['review']);

        $this->postJson(route('review.sessions.figures.store', $session), [
            'level_content_ids' => [$figures[0]->id, $figures[1]->id],
        ])->assertOk();

        $this->assertDatabaseHas('review_session_figures', [
            'review_session_id' => $session->id,
            'level_content_id' => $figures[0]->id,
            'selected_by_student' => true,
        ]);
    }

    public function test_songs_endpoint_assigns_three_recommendations(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $session = ReviewSession::factory()->create([
            'student_id' => $student->id,
            'level_id' => $level->id,
            'expires_at' => now()->addMinutes(5),
        ]);
        $songs = RecommendedSong::factory()->count(4)->create();

        foreach ($songs as $song) {
            $song->levels()->attach($level);
        }

        Sanctum::actingAs($student, ['review']);

        $this->getJson(route('review.sessions.songs', $session))
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->assertSame(3, $session->fresh()->songs()->count());
    }

    public function test_quiz_next_returns_question_without_correct_flags(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $session = ReviewSession::factory()->create([
            'student_id' => $student->id,
            'level_id' => $level->id,
            'expires_at' => now()->addMinutes(5),
        ]);
        $quizItem = QuizItem::factory()->figure()->for($level)->create();
        QuizOption::factory()->for($quizItem)->correct()->create(['description' => 'Correcta']);
        QuizOption::factory()->count(2)->for($quizItem)->create();

        Sanctum::actingAs($student, ['review']);

        $response = $this->getJson(route('review.sessions.quiz.next', $session));

        $response->assertOk()
            ->assertJsonPath('data.id', $quizItem->id)
            ->assertJsonCount(3, 'data.options')
            ->assertJsonMissing(['is_correct' => true]);
    }

    public function test_quiz_answer_returns_feedback(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $session = ReviewSession::factory()->create([
            'student_id' => $student->id,
            'level_id' => $level->id,
            'expires_at' => now()->addMinutes(5),
        ]);
        $quizItem = QuizItem::factory()->figure()->for($level)->create();
        $correctOption = QuizOption::factory()->for($quizItem)->correct()->create();
        QuizOption::factory()->count(2)->for($quizItem)->create();

        Sanctum::actingAs($student, ['review']);

        $this->postJson(route('review.sessions.quiz.answer', [$session, $quizItem]), [
            'quiz_option_id' => $correctOption->id,
        ])->assertOk()
            ->assertJsonPath('data.is_correct', true);
    }

    public function test_complete_updates_session_and_streak(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $session = ReviewSession::factory()->create([
            'student_id' => $student->id,
            'level_id' => $level->id,
            'expires_at' => now()->addMinutes(5),
        ]);
        $this->attachSelectedFigures($session, $level);

        Sanctum::actingAs($student, ['review']);

        $this->postJson(route('review.sessions.complete', $session))
            ->assertOk()
            ->assertJsonPath('data.session.completed', true)
            ->assertJsonPath('data.streak.current_streak', 1);

        $this->assertNotNull($session->fresh()->completed_at);
    }

    public function test_streak_endpoint_returns_current_streak(): void
    {
        [$student] = $this->createEnrolledStudent();
        StudentStreak::factory()->create([
            'student_id' => $student->id,
            'current_streak' => 5,
            'last_review_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($student, ['review']);

        $this->getJson(route('review.streak'))
            ->assertOk()
            ->assertJsonPath('data.current_streak', 5);
    }

    public function test_logout_revokes_current_token(): void
    {
        [$student] = $this->createEnrolledStudent();
        $token = $student->createToken('review-panel', ['review']);
        $plainTextToken = $token->plainTextToken;

        $this->withToken($plainTextToken)
            ->deleteJson(route('review.logout'))
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);

        Auth::forgetGuards();

        $this->withToken($plainTextToken)
            ->getJson(route('review.streak'))
            ->assertUnauthorized();
    }

    public function test_expired_session_blocks_figures_but_allows_songs_and_complete(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $session = ReviewSession::factory()->expired()->create([
            'student_id' => $student->id,
            'level_id' => $level->id,
        ]);
        $songs = RecommendedSong::factory()->count(4)->create();

        foreach ($songs as $song) {
            $song->levels()->attach($level);
        }

        $this->attachSelectedFigures($session, $level);

        Sanctum::actingAs($student, ['review']);

        $this->getJson(route('review.sessions.figure-options', $session))->assertGone();
        $this->getJson(route('review.sessions.songs', $session))
            ->assertOk()
            ->assertJsonCount(3, 'data');
        $this->postJson(route('review.sessions.complete', $session))
            ->assertOk()
            ->assertJsonPath('data.session.completed', true);
    }

    public function test_create_session_resumes_active_session(): void
    {
        [$student, $level] = $this->createEnrolledStudent(['review_duration_seconds' => 1800]);
        Sanctum::actingAs($student, ['review']);

        $first = $this->postJson(route('review.sessions.store'))
            ->assertCreated()
            ->json('data.id');

        $second = $this->postJson(route('review.sessions.store'))
            ->assertOk()
            ->json('data.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, ReviewSession::query()->where('student_id', $student->id)->count());
        $this->assertSame($level->id, ReviewSession::query()->find($first)->level_id);
    }

    public function test_create_session_rejects_second_session_same_day(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        ReviewSession::factory()->create([
            'student_id' => $student->id,
            'level_id' => $level->id,
            'started_at' => now(),
            'expires_at' => now()->subMinute(),
            'completed' => true,
        ]);

        Sanctum::actingAs($student, ['review']);

        $this->postJson(route('review.sessions.store'))
            ->assertConflict()
            ->assertJsonPath('message', 'Ya usaste tu repaso de hoy. Vuelve mañana.')
            ->assertJsonPath('locked', true);
    }

    public function test_current_session_endpoint_returns_active_session(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $session = ReviewSession::factory()->create([
            'student_id' => $student->id,
            'level_id' => $level->id,
            'expires_at' => now()->addMinutes(20),
            'completed' => false,
        ]);

        Sanctum::actingAs($student, ['review']);

        $this->getJson(route('review.sessions.current'))
            ->assertOk()
            ->assertJsonPath('data.id', $session->id);
    }

    /**
     * @return Collection<int, LevelContent>
     */
    private function attachSelectedFigures(ReviewSession $session, Level $level): Collection
    {
        $figures = LevelContent::factory()->count(2)->for($level)->create();

        $session->figures()->attach(
            $figures->mapWithKeys(fn (LevelContent $figure): array => [
                $figure->id => ['selected_by_student' => true],
            ])->all(),
        );

        return $figures;
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
        ]);

        return [$student, $level];
    }
}
