<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Support\BillingPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Notification;

/**
 * Bell-only summary for payments staff once a round of reminders went out.
 */
class PaymentRemindersSentNotification extends Notification
{
    public function __construct(
        public CarbonImmutable $period,
        public string $kind,
        public int $count,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'period' => BillingPeriod::key($this->period),
            'period_label' => BillingPeriod::label($this->period),
            'kind' => $this->kind,
            'count' => $this->count,
        ];
    }
}
