<?php

namespace Database\Factories;

use App\Models\Level;
use App\Models\ReviewSession;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewSession>
 */
class ReviewSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-1 week', 'now');
        $expiresAt = (clone $startedAt)->modify('+5 minutes');

        return [
            'student_id' => Student::factory(),
            'level_id' => Level::factory(),
            'started_at' => $startedAt,
            'expires_at' => $expiresAt,
            'completed' => false,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'completed' => true,
        ]);
    }

    public function expired(): static
    {
        return $this->state(function (): array {
            $startedAt = now()->subMinutes(10);
            $expiresAt = now()->subMinutes(5);

            return [
                'started_at' => $startedAt,
                'expires_at' => $expiresAt,
            ];
        });
    }
}
