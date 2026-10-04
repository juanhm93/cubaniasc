<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Enums\PlatformAbility;
use App\Models\OneTimeSession;
use App\Models\User;
use App\Notifications\NewOneTimeSessionNotification;
use App\Support\NotificationRecipients;
use App\Support\SafeNotifier;

/**
 * Workshop, private class or event scheduled: its teacher gets the
 * assignment email and the rest of the staff see it in the bell.
 */
final class NotifyOneTimeSessionCreated
{
    public function __construct(private readonly NotifyTeacherAssigned $notifyTeacherAssigned) {}

    public function execute(OneTimeSession $session, ?User $actor): void
    {
        $this->notifyTeacherAssigned->execute($session, $actor);

        SafeNotifier::afterResponse(function () use ($session, $actor): void {
            $session->loadMissing('teacher');

            $staff = NotificationRecipients::combine(
                $actor,
                NotificationRecipients::withAbility(PlatformAbility::OneTimeSessions),
            )->reject(fn (User $user): bool => $user->id === (int) $session->user_id);

            SafeNotifier::notifyEach($staff, new NewOneTimeSessionNotification($session, $actor?->name));
        });
    }
}
