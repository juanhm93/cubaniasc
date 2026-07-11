<?php

namespace Tests\Feature\Review;

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\LevelContent;
use App\Models\QuizItem;
use App\Models\QuizOption;
use App\Models\RecommendedSong;
use App\Models\ReviewSession;
use App\Models\Student;
use App\Models\StudentStreak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewPanelApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_identify_returns_token_and_student_data_for_enrolled_student(): void
    {
        [$student, $level] = $this->createEnrolledStudent();

        $response = $this->postJson(route('review.identify'), [
            'email' => $student->email,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'student' => ['id', 'name', 'email', 'dni'],
                    'level' => ['id', 'name', 'slug', 'review_duration_seconds'],
                ],
            ])
            ->assertJsonPath('data.student.id', $student->id)
            ->assertJsonPath('data.level.id', $level->id);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => Student::class,
            'tokenable_id' => $student->id,
            'name' => 'review-panel',
        ]);
    }

    public function test_identify_returns_not_found_for_unenrolled_student(): void
    {
        Student::factory()->create(['email' => 'solo@example.com']);

        $this->postJson(route('review.identify'), [
            'email' => 'solo@example.com',
        ])->assertNotFound();
    }

    public function test_authenticated_student_can_create_review_session(): void
    {
        [$student, $level] = $this->createEnrolledStudent(['review_duration_seconds' => 420]);
        Sanctum::actingAs($student);

        $response = $this->postJson(route('review.sessions.store'));

        $response->assertCreated()
            ->assertJsonPath('data.level_id', $level->id)
            ->assertJsonPath('data.completed', false);

        $this->assertGreaterThanOrEqual(419, $response->json('data.remaining_seconds'));
    }

    public function test_student_can_view_own_session_state(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $session = ReviewSession::factory()->create([
            'student_id' => $student->id,
            'level_id' => $level->id,
            'expires_at' => now()->addMinutes(5),
        ]);

        Sanctum::actingAs($student);

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

        Sanctum::actingAs($student);

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

        Sanctum::actingAs($student);

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

        Sanctum::actingAs($student);

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

        Sanctum::actingAs($student);

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

        Sanctum::actingAs($student);

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

        Sanctum::actingAs($student);

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

        Sanctum::actingAs($student);

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

        Sanctum::actingAs($student);

        $this->postJson(route('review.sessions.complete', $session))
            ->assertOk()
            ->assertJsonPath('data.session.completed', true)
            ->assertJsonPath('data.streak.current_streak', 1);
    }

    public function test_streak_endpoint_returns_current_streak(): void
    {
        [$student] = $this->createEnrolledStudent();
        StudentStreak::factory()->create([
            'student_id' => $student->id,
            'current_streak' => 5,
        ]);

        Sanctum::actingAs($student);

        $this->getJson(route('review.streak'))
            ->assertOk()
            ->assertJsonPath('data.current_streak', 5);
    }

    public function test_logout_revokes_current_token(): void
    {
        [$student] = $this->createEnrolledStudent();
        $token = $student->createToken('review-panel');
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

    public function test_expired_session_endpoints_are_rejected(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $session = ReviewSession::factory()->expired()->create([
            'student_id' => $student->id,
            'level_id' => $level->id,
        ]);

        Sanctum::actingAs($student);

        $this->getJson(route('review.sessions.figure-options', $session))->assertGone();
        $this->getJson(route('review.sessions.songs', $session))->assertGone();
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
