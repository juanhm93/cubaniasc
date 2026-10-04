<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Enums\PlatformAbility;
use App\Models\User;
use App\Notifications\NewPendingUserNotification;
use App\Support\NotificationRecipients;
use App\Support\SafeNotifier;

/**
 * Someone registered: whoever can approve accounts is told.
 */
final class NotifyUserRegistered
{
    public function execute(User $user): void
    {
        SafeNotifier::afterResponse(fn () => SafeNotifier::notifyEach(
            NotificationRecipients::combine($user, NotificationRecipients::withAbility(PlatformAbility::AdminUsers)),
            new NewPendingUserNotification($user),
        ));
    }
}
