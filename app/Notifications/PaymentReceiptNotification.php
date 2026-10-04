<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\BillingPeriod;
use App\Support\CourseSummary;
use App\Support\NotificationFormat;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Payment receipt for the student.
 */
class PaymentReceiptNotification extends Notification
{
    public function __construct(public Payment $payment) {}

    /**
     * Students have no notification bell, only email.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('✅ Recibimos tu pago — '.config('app.name'))
            ->markdown('mail.payments.receipt', [
                'payment' => $this->payment,
                'studentName' => $this->payment->student?->name,
                'courseLabel' => CourseSummary::label($this->payment->course),
                'amount' => NotificationFormat::amount($this->payment->amount),
                'periodLabel' => $this->payment->period !== null ? BillingPeriod::label($this->payment->period) : null,
                'method' => NotificationFormat::paymentMethod($this->payment->method),
                'paidAt' => NotificationFormat::dateTime($this->payment->paid_at),
                'isPartial' => $this->payment->status === PaymentStatus::Partial,
                'reviewUrl' => route('review-panel'),
            ]);
    }
}
