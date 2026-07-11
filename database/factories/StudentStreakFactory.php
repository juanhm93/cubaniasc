<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\StudentStreak;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentStreak>
 */
class StudentStreakFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'current_streak' => fake()->numberBetween(0, 14),
            'last_review_at' => fake()->optional(0.8)->dateTimeBetween('-2 weeks', 'now'),
        ];
    }
}
