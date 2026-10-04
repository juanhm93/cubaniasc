<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\User;
use App\Notifications\AccountApprovedNotification;
use App\Support\SafeNotifier;

/**
 * A pending account was activated: its owner can now sign in.
 */
final class NotifyUserApproved
{
    public function execute(User $user): void
    {
        SafeNotifier::afterResponse(fn () => $user->notify(new AccountApprovedNotification));
    }
}
