<?php

namespace Database\Factories;

use App\Models\LevelContent;
use App\Models\Student;
use App\Models\StudentFigureView;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentFigureView>
 */
class StudentFigureViewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'level_content_id' => LevelContent::factory(),
            'viewed_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ];
    }
}
