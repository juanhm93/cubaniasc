<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\Course;
use App\Models\OneTimeSession;
use App\Models\User;
use App\Notifications\TeacherAssignedNotification;
use App\Support\SafeNotifier;

/**
 * A teacher was put in charge of a course or a one-time session. Nothing is
 * sent when teachers assign themselves or the teacher account is not active.
 */
final class NotifyTeacherAssigned
{
    public function execute(Course|OneTimeSession $assignment, ?User $actor): void
    {
        $teacherId = $assignment->user_id !== null ? (int) $assignment->user_id : null;

        if ($teacherId === null || $teacherId === $actor?->id) {
            return;
        }

        SafeNotifier::afterResponse(function () use ($assignment, $actor): void {
            $assignment->loadMissing(['teacher', 'place']);

            if ($assignment instanceof Course) {
                $assignment->loadMissing(['level.danceType', 'scheduleSlots']);
            }

            $teacher = $assignment->teacher;

            if ($teacher === null || ! $teacher->isActive()) {
                return;
            }

            $teacher->notify(new TeacherAssignedNotification($assignment, $actor?->name));
        });
    }
}
