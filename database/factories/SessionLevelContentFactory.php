<?php

namespace Database\Factories;

use App\Models\CourseSession;
use App\Models\LevelContent;
use App\Models\SessionLevelContent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionLevelContent>
 */
class SessionLevelContentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_session_id' => CourseSession::factory(),
            'level_content_id' => LevelContent::factory(),
            'notes' => null,
        ];
    }
}
