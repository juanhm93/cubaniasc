<?php

namespace Database\Seeders;

use App\Enums\PaymentStatus;
use App\Models\Course;
use App\Models\CourseScheduleSlot;
use App\Models\CourseSession;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\Payment;
use App\Models\Place;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;

class PaymentsDemoSeeder extends Seeder
{
    /**
     * Demo enrollments and payments for the admin Pagos table (skipped if data exists).
     */
    public function run(): void
    {
        if (Enrollment::query()->exists()) {
            return;
        }

        $admin = User::query()->whereHas('role', fn ($query) => $query->where('slug', 'admin'))->first();

        if ($admin === null || $admin->company_id === null) {
            return;
        }

        $level = Level::query()->first();

        if ($level === null) {
            return;
        }

        $place = Place::factory()->create(['company_id' => $admin->company_id]);

        $course = Course::query()->create([
            'level_id' => $level->id,
            'schedule_id' => null,
            'company_id' => $admin->company_id,
            'place_id' => $place->id,
            'user_id' => $admin->id,
            'price' => 120.00,
            'is_active' => true,
        ]);

        CourseScheduleSlot::query()->create([
            'course_id' => $course->id,
            'weekday' => 3,
            'starts_at' => '19:00:00',
            'ends_at' => '20:30:00',
            'sort_order' => 0,
        ]);

        foreach (range(1, 10) as $weekOffset) {
            CourseSession::query()->create([
                'course_id' => $course->id,
                'session_date' => now()->subWeeks(11 - $weekOffset)->startOfWeek()->addDays(2)->toDateString(),
                'starts_at' => '19:00',
                'ends_at' => '20:30',
                'notes' => null,
            ]);
        }

        $students = Student::factory()->count(5)->create();

        foreach ($students as $student) {
            Enrollment::factory()->create([
                'course_id' => $course->id,
                'student_id' => $student->id,
            ]);

            Payment::query()->create([
                'amount' => fake()->randomFloat(2, 25, 180),
                'reference' => fake()->optional(0.6)->bothify('REF-####-????'),
                'receipt_path' => null,
                'course_id' => $course->id,
                'student_id' => $student->id,
                'status' => PaymentStatus::Paid,
                'due_at' => null,
                'paid_at' => now()->subDays(fake()->numberBetween(0, 120)),
                'method' => fake()->randomElement(['efectivo', 'transferencia', 'otro']),
                'notes' => null,
            ]);
        }
    }
}
