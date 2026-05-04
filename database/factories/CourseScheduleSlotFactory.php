<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseScheduleSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseScheduleSlot>
 */
class CourseScheduleSlotFactory extends Factory
{
    protected $model = CourseScheduleSlot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'weekday' => fake()->numberBetween(1, 7),
            'starts_at' => '17:00:00',
            'ends_at' => '18:30:00',
            'sort_order' => 0,
        ];
    }
}
