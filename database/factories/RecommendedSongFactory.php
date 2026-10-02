<?php

namespace Database\Factories;

use App\Models\RecommendedSong;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecommendedSong>
 */
class RecommendedSongFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->words(3, true),
            'artist' => fake()->name(),
            'audio_or_link_url' => fake()->url(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
