<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Payment;
use App\Support\BillingPeriod;
use App\Support\CourseSummary;
use App\Support\NotificationFormat;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Staff alert: a payment was registered.
 *
 * Sent synchronously on purpose (no queue worker in production).
 */
class PaymentRecordedNotification extends Notification
{
    public function __construct(
        public Payment $payment,
        public ?string $actorName = null,
    ) {}

    /**
     * Database first so the bell works even when the mail server is down.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('💸 Pago registrado: '.$this->payment->student?->name.' — '.NotificationFormat::amount($this->payment->amount))
            ->markdown('mail.payments.recorded', [
                'payment' => $this->payment,
                'studentName' => $this->payment->student?->name,
                'courseLabel' => CourseSummary::label($this->payment->course),
                'amount' => NotificationFormat::amount($this->payment->amount),
                'periodLabel' => $this->periodLabel(),
                'method' => NotificationFormat::paymentMethod($this->payment->method),
                'paidAt' => NotificationFormat::dateTime($this->payment->paid_at),
                'actorName' => $this->actorName,
                'actionUrl' => route('admin.payments.index', ['month' => $this->periodKey()]),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'payment_id' => $this->payment->id,
            'student_id' => $this->payment->student_id,
            'student_name' => $this->payment->student?->name,
            'course_id' => $this->payment->course_id,
            'course_label' => CourseSummary::label($this->payment->course),
            'amount' => NotificationFormat::amount($this->payment->amount),
            'period' => $this->periodKey(),
            'period_label' => $this->periodLabel(),
            'actor_name' => $this->actorName,
        ];
    }

    private function periodKey(): ?string
    {
        return $this->payment->period !== null ? BillingPeriod::key($this->payment->period) : null;
    }

    private function periodLabel(): ?string
    {
        return $this->payment->period !== null ? BillingPeriod::label($this->payment->period) : null;
    }
}
