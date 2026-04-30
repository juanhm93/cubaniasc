<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseSession>
 */
class CourseSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'session_date' => fake()->date(),
            'starts_at' => null,
            'ends_at' => null,
            'notes' => null,
        ];
    }
}
