<?php

namespace Database\Factories;

use App\Enums\QuizItemType;
use App\Models\Level;
use App\Models\QuizItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizItem>
 */
class QuizItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(QuizItemType::cases()),
            'prompt' => fake()->sentence(4),
            'level_id' => Level::factory(),
            'is_active' => true,
        ];
    }

    public function figure(): static
    {
        return $this->state(fn (): array => [
            'type' => QuizItemType::Figure,
        ]);
    }

    public function funFact(): static
    {
        return $this->state(fn (): array => [
            'type' => QuizItemType::FunFact,
        ]);
    }

    public function global(): static
    {
        return $this->state(fn (): array => [
            'level_id' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
