<?php

namespace Database\Factories;

use App\Enums\PreRegistrationCountry;
use App\Models\PreRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PreRegistration>
 */
class PreRegistrationFactory extends Factory
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
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->numerify('+58##########'),
            'country' => fake()->optional()->randomElement(PreRegistrationCountry::cases()),
            'agree' => true,
            'message' => fake()->optional(0.4)->paragraph(),
        ];
    }
}
