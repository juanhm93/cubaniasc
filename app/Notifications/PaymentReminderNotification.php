<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Course;
use App\Support\BillingPeriod;
use App\Support\CourseSummary;
use App\Support\NotificationFormat;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Monthly payment reminder for the student: one email listing every course
 * still unpaid for the upcoming month.
 */
class PaymentReminderNotification extends Notification
{
    /**
     * @param  Collection<int, Course>  $courses
     */
    public function __construct(
        public Collection $courses,
        public CarbonImmutable $period,
        public string $kind,
    ) {}

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
        $periodLabel = BillingPeriod::label($this->period);
        $isLastDay = $this->kind === BillingPeriod::REMINDER_LAST_DAY;

        return (new MailMessage)
            ->subject($isLastDay
                ? '⏰ Mañana empieza '.$periodLabel.': recuerda tu pago'
                : '🗓️ Ya puedes pagar '.$periodLabel.' — '.config('app.name'))
            ->markdown('mail.payments.reminder', [
                'studentName' => $notifiable->name ?? null,
                'periodLabel' => $periodLabel,
                'isLastDay' => $isLastDay,
                'courses' => $this->courses->map(fn (Course $course): array => [
                    'label' => CourseSummary::label($course),
                    'price' => NotificationFormat::amount($course->price),
                ])->values()->all(),
                'total' => NotificationFormat::amount($this->courses->sum(fn (Course $course): float => (float) $course->price)),
            ]);
    }
}
