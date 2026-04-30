<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Course;
use App\Models\Level;
use App\Models\Place;
use App\Models\Schedule;
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
            'schedule_id' => Schedule::factory(),
            'company_id' => $company->id,
            'place_id' => Place::factory()->for($company),
            'user_id' => User::factory(),
            'price' => fake()->randomFloat(2, 25, 600),
        ];
    }
}
