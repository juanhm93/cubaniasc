<?php

namespace Tests\Feature\Review;

use App\Exceptions\Review\ReviewSessionExpiredException;
use App\Models\Level;
use App\Models\QuizItem;
use App\Models\QuizOption;
use App\Models\ReviewSession;
use App\Services\Review\QuizGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizGeneratorServiceTest extends TestCase
{
    use RefreshDatabase;

    private QuizGeneratorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(QuizGeneratorService::class);
    }

    public function test_returns_next_question_for_active_session(): void
    {
        $level = Level::factory()->create();
        $session = ReviewSession::factory()->create([
            'level_id' => $level->id,
            'expires_at' => now()->addMinutes(5),
        ]);
        $quizItem = QuizItem::factory()->figure()->for($level)->create();
        QuizOption::factory()->for($quizItem)->correct()->create();
        QuizOption::factory()->count(2)->for($quizItem)->create();

        $question = $this->service->nextQuestion($session);

        $this->assertNotNull($question);
        $this->assertTrue($quizItem->is($question));
        $this->assertCount(3, $question->options);
    }

    public function test_includes_global_fun_facts_in_pool(): void
    {
        $level = Level::factory()->create();
        $session = ReviewSession::factory()->create([
            'level_id' => $level->id,
            'expires_at' => now()->addMinutes(5),
        ]);
        $funFact = QuizItem::factory()->funFact()->global()->create();
        QuizOption::factory()->for($funFact)->correct()->create();
        QuizOption::factory()->count(2)->for($funFact)->create();

        $question = $this->service->nextQuestion($session);

        $this->assertTrue($funFact->is($question));
    }

    public function test_returns_null_after_pool_is_exhausted_without_recycling(): void
    {
        $level = Level::factory()->create();
        $session = ReviewSession::factory()->create([
            'level_id' => $level->id,
            'expires_at' => now()->addMinutes(5),
        ]);
        $quizItem = QuizItem::factory()->figure()->for($level)->create();
        $correctOption = QuizOption::factory()->for($quizItem)->correct()->create();
        QuizOption::factory()->count(2)->for($quizItem)->create();

        $first = $this->service->nextQuestion($session);
        $this->service->recordAnswer($session, $quizItem, $correctOption);
        $second = $this->service->nextQuestion($session);

        $this->assertTrue($quizItem->is($first));
        $this->assertNull($second);
    }

    public function test_returns_null_after_max_quiz_questions(): void
    {
        $level = Level::factory()->create();
        $session = ReviewSession::factory()->create([
            'level_id' => $level->id,
            'expires_at' => now()->addMinutes(30),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $quizItem = QuizItem::factory()->figure()->for($level)->create();
            $correctOption = QuizOption::factory()->for($quizItem)->correct()->create();
            QuizOption::factory()->count(2)->for($quizItem)->create();

            $question = $this->service->nextQuestion($session);
            $this->assertNotNull($question);
            $this->service->recordAnswer($session, $question, $correctOption);
        }

        $this->assertNull($this->service->nextQuestion($session));
    }

    public function test_returns_null_when_session_is_expired(): void
    {
        $session = ReviewSession::factory()->expired()->create();

        $this->assertNull($this->service->nextQuestion($session));
    }

    public function test_records_answer_for_active_session(): void
    {
        $session = ReviewSession::factory()->create(['expires_at' => now()->addMinutes(5)]);
        $quizItem = QuizItem::factory()->for($session->level)->create();
        $correctOption = QuizOption::factory()->for($quizItem)->correct()->create();
        QuizOption::factory()->count(2)->for($quizItem)->create();

        $response = $this->service->recordAnswer($session, $quizItem, $correctOption);

        $this->assertDatabaseHas('review_quiz_responses', [
            'id' => $response->id,
            'review_session_id' => $session->id,
            'quiz_item_id' => $quizItem->id,
            'is_correct' => true,
        ]);
    }

    public function test_throws_when_recording_answer_on_expired_session(): void
    {
        $session = ReviewSession::factory()->expired()->create();
        $quizItem = QuizItem::factory()->for($session->level)->create();
        $option = QuizOption::factory()->for($quizItem)->correct()->create();

        $this->expectException(ReviewSessionExpiredException::class);

        $this->service->recordAnswer($session, $quizItem, $option);
    }
}
