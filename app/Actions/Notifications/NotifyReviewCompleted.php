<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\ReviewSession;
use App\Models\Student;
use App\Notifications\ReviewCompletedNotification;
use App\Notifications\StudentReviewCompletedNotification;
use App\Support\NotificationRecipients;
use App\Support\SafeNotifier;

/**
 * Daily review finished: the student gets a cheering email, and their
 * teachers plus owner and admins see it in the bell (no staff email, it
 * happens every day).
 */
final class NotifyReviewCompleted
{
    public function execute(Student $student, ReviewSession $session, int $streak): void
    {
        SafeNotifier::afterResponse(function () use ($student, $session, $streak): void {
            $session->loadMissing(['level', 'selectedFigures']);

            $activeCourses = Course::query()
                ->where('is_active', true)
                ->whereHas('enrollments', fn ($query) => $query
                    ->where('student_id', $student->id)
                    ->where('status', EnrollmentStatus::Active))
                ->orderBy('id')
                ->get();

            SafeNotifier::notifyEach(
                NotificationRecipients::combine(
                    null,
                    NotificationRecipients::teachersOf($activeCourses),
                    NotificationRecipients::ownersAndAdmins(),
                ),
                new StudentReviewCompletedNotification($student, $session, $streak, $activeCourses->first()?->id),
            );

            SafeNotifier::attempt(fn () => $student->notify(new ReviewCompletedNotification($session, $streak)));
        });
    }
}
