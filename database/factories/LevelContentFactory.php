<?php

namespace Database\Factories;

use App\Models\Level;
use App\Models\LevelContent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
            'video_url' => fake()->optional()->url(),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * A figure with its own MP4 already confirmed. Once created, the object key
     * follows the real layout `level-contents/{id}/{uuid}.mp4`.
     */
    public function withVideoFile(): static
    {
        return $this->state(fn (): array => [
            'video_file_path' => 'level-contents/0/'.Str::uuid().'.mp4',
            'video_file_size_bytes' => fake()->numberBetween(5_000_000, 80_000_000),
            'video_file_duration_seconds' => fake()->numberBetween(20, 180),
            'video_file_version' => 1,
            'video_file_uploaded_at' => now(),
        ])->afterCreating(function (LevelContent $content): void {
            $content->forceFill([
                'video_file_path' => "level-contents/{$content->id}/".Str::uuid().'.mp4',
            ])->saveQuietly();
        });
    }
}
