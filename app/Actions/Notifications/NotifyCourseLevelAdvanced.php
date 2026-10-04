<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Level;
use App\Models\Student;
use App\Models\User;
use App\Notifications\CourseLevelAdvancedNotification;
use App\Notifications\CourseLevelUpNotification;
use App\Support\NotificationRecipients;
use App\Support\SafeNotifier;

/**
 * A course moved on to the next level: owner, admins and the teacher are
 * alerted, and every active student gets a celebration email.
 */
final class NotifyCourseLevelAdvanced
{
    public function execute(Course $course, Level $previousLevel, ?User $actor): void
    {
        SafeNotifier::afterResponse(function () use ($course, $previousLevel, $actor): void {
            $course->load(['level.danceType', 'scheduleSlots', 'teacher']);
            $newLevel = $course->level;

            if ($newLevel === null) {
                return;
            }

            $students = Student::query()
                ->whereHas('enrollments', fn ($query) => $query
                    ->where('course_id', $course->id)
                    ->where('status', EnrollmentStatus::Active))
                ->orderBy('id')
                ->get();

            SafeNotifier::notifyEach(
                NotificationRecipients::combine(
                    $actor,
                    NotificationRecipients::ownersAndAdmins(),
                    NotificationRecipients::teachersOf([$course]),
                ),
                new CourseLevelAdvancedNotification($course, $previousLevel, $newLevel, $students->count(), $actor?->name),
            );

            SafeNotifier::notifyEach($students, new CourseLevelUpNotification($course, $previousLevel, $newLevel));
        });
    }
}
