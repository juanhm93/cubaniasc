<?php

declare(strict_types=1);

namespace App\Actions\Enrollments;

use App\Enums\EnrollmentStatus;
use App\Mail\EnrollmentWelcomeMail;
use App\Models\Enrollment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Enrolls a student in a course and welcomes them by email.
 *
 * Every admin flow that creates an enrollment goes through here so the
 * welcome email is sent exactly once, wherever the enrollment comes from.
 */
final class EnrollStudentInCourse
{
    public function execute(int $studentId, int $courseId): Enrollment
    {
        $enrollment = Enrollment::query()->create([
            'course_id' => $courseId,
            'student_id' => $studentId,
            'status' => EnrollmentStatus::Active,
            'enrolled_at' => now(),
        ]);

        DB::afterCommit(fn () => $this->sendWelcomeMail($enrollment));

        return $enrollment;
    }

    /**
     * Runs once the surrounding transaction commits, or right away outside one.
     *
     * Sent synchronously because production has no queue worker. A saved
     * enrollment is worth more than a delivered email, so a mail failure is
     * reported and swallowed instead of breaking the admin flow.
     */
    private function sendWelcomeMail(Enrollment $enrollment): void
    {
        try {
            $email = $enrollment->student?->email;

            if (blank($email)) {
                return;
            }

            Mail::to($email)->send(new EnrollmentWelcomeMail($enrollment));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
