<?php

namespace Database\Factories;

use App\Models\DanceType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DanceType>
 */
class DanceTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999),
            'description' => fake()->optional()->sentence(),
            'sort_order' => fake()->unique()->numberBetween(1, 99),
        ];
    }
}
