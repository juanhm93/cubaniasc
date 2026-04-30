<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'dni' => fake()->optional(0.7)->numerify('########'),
            'email' => fake()->unique()->safeEmail(),
            'birthday' => fake()->optional(0.5)->date(),
        ];
    }
}
