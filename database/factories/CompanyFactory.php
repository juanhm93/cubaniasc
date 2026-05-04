<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'rif' => fake()->numerify('J-########-#'),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->optional()->numerify('+58##########'),
            'address' => fake()->optional()->address(),
            'logo' => null,
            'website' => fake()->optional()->url(),
            'is_active' => true,
        ];
    }
}
