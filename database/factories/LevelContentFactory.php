<?php

namespace Database\Factories;

use App\Models\Level;
use App\Models\LevelContent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LevelContent>
 */
class LevelContentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'level_id' => Level::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
