<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Enums\PaymentStatus;
use App\Enums\PlatformAbility;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentReceiptNotification;
use App\Notifications\PaymentRecordedNotification;
use App\Support\NotificationRecipients;
use App\Support\SafeNotifier;

/**
 * Payment registered: payments staff are alerted and, when money actually
 * came in (paid or partial), the student gets a receipt.
 */
final class NotifyPaymentRecorded
{
    public function execute(Payment $payment, ?User $actor): void
    {
        SafeNotifier::afterResponse(function () use ($payment, $actor): void {
            $payment->loadMissing(['student', 'course.level.danceType']);

            $staff = NotificationRecipients::combine(
                $actor,
                NotificationRecipients::withAbility(PlatformAbility::Payments),
            );

            SafeNotifier::notifyEach($staff, new PaymentRecordedNotification($payment, $actor?->name));

            $moneyCameIn = in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::Partial], true);

            if ($moneyCameIn && $payment->student !== null) {
                SafeNotifier::attempt(fn () => $payment->student->notify(new PaymentReceiptNotification($payment)));
            }
        });
    }
}
