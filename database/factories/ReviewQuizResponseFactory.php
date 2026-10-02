<?php

namespace Database\Factories;

use App\Models\QuizItem;
use App\Models\QuizOption;
use App\Models\ReviewQuizResponse;
use App\Models\ReviewSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewQuizResponse>
 */
class ReviewQuizResponseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quizItem = QuizItem::factory()->create();
        $correctOption = QuizOption::factory()->for($quizItem)->correct()->create();
        QuizOption::factory()->count(2)->for($quizItem)->create();

        return [
            'review_session_id' => ReviewSession::factory(),
            'quiz_item_id' => $quizItem->id,
            'quiz_option_id' => $correctOption->id,
            'is_correct' => true,
            'answered_at' => fake()->dateTimeBetween('-1 hour', 'now'),
        ];
    }
}
