<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Enums\PlatformAbility;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\EnrollmentWelcomeNotification;
use App\Notifications\NewEnrollmentNotification;
use App\Support\NotificationRecipients;
use App\Support\SafeNotifier;

/**
 * Formal enrollment: students staff and the course teacher are alerted, and
 * the student gets a welcome email.
 */
final class NotifyEnrollmentCreated
{
    public function execute(Enrollment $enrollment, ?User $actor): void
    {
        SafeNotifier::afterResponse(function () use ($enrollment, $actor): void {
            $enrollment->loadMissing([
                'student',
                'course.level.danceType',
                'course.scheduleSlots',
                'course.place',
                'course.teacher',
            ]);

            $staff = NotificationRecipients::combine(
                $actor,
                NotificationRecipients::withAbility(PlatformAbility::Students),
                NotificationRecipients::teachersOf([$enrollment->course]),
            );

            SafeNotifier::notifyEach($staff, new NewEnrollmentNotification($enrollment, $actor?->name));

            if ($enrollment->student !== null) {
                SafeNotifier::attempt(fn () => $enrollment->student->notify(new EnrollmentWelcomeNotification($enrollment)));
            }
        });
    }
}
