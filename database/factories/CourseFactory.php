<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Course;
use App\Models\CourseScheduleSlot;
use App\Models\Level;
use App\Models\Place;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $company = Company::factory()->create();

        return [
            'level_id' => Level::factory(),
            'schedule_id' => null,
            'company_id' => $company->id,
            'place_id' => Place::factory()->for($company),
            'user_id' => User::factory(),
            'price' => fake()->randomFloat(2, 25, 600),
            'is_active' => true,
            'review_custom_settings' => false,
        ];
    }

    /**
     * Course with its own review settings instead of the normal ones.
     *
     * @param  array{review_previous_levels_count?: int|null, review_figure_options_count?: int, review_figure_selection_count?: int, review_duration_seconds?: int}  $overrides
     */
    public function customReview(array $overrides = []): static
    {
        return $this->state(fn (): array => [
            'review_custom_settings' => true,
            'review_previous_levels_count' => null,
            'review_figure_options_count' => 4,
            'review_figure_selection_count' => 2,
            'review_duration_seconds' => 1800,
            ...$overrides,
        ]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Course $course): void {
            CourseScheduleSlot::query()->create([
                'course_id' => $course->id,
                'weekday' => fake()->randomElement([1, 3, 5]),
                'starts_at' => '18:00:00',
                'ends_at' => '19:30:00',
                'sort_order' => 0,
            ]);
        });
    }
}
