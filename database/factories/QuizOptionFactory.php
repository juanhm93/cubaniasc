<?php

namespace Database\Factories;

use App\Models\QuizItem;
use App\Models\QuizOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizOption>
 */
class QuizOptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quiz_item_id' => QuizItem::factory(),
            'description' => fake()->sentence(6),
            'is_correct' => false,
        ];
    }

    public function correct(): static
    {
        return $this->state(fn (): array => [
            'is_correct' => true,
        ]);
    }
}
