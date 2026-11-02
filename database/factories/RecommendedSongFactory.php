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
            'audio_file_path' => 'musica-salsa-casino/'.fake()->unique()->slug().'.mp3',
            'audio_file_size_bytes' => 8_000_000,
            'audio_file_duration_seconds' => 240,
            'audio_file_version' => 1,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }

    /**
     * A song from before the bucket: only the external link.
     */
    public function withoutAudioFile(): static
    {
        return $this->state(fn (): array => [
            'audio_or_link_url' => fake()->url(),
            'audio_file_path' => null,
            'audio_file_size_bytes' => null,
            'audio_file_duration_seconds' => null,
            'audio_file_version' => 0,
        ]);
    }
}
