<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Course;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'amount' => fake()->randomFloat(2, 10, 500),
            'reference' => fake()->optional()->bothify('REF-####-????'),
            'course_id' => Course::factory(),
            'student_id' => Student::factory(),
            'status' => PaymentStatus::Pending,
            'due_at' => now()->addDays(7),
            'paid_at' => null,
            'method' => null,
            'notes' => null,
        ];
    }
}
