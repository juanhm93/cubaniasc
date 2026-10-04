<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Enrollment;
use App\Support\CourseSummary;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Welcome email for the student once the enrollment is formal.
 */
class EnrollmentWelcomeNotification extends Notification
{
    public function __construct(public Enrollment $enrollment) {}

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
        $course = $this->enrollment->course;

        return (new MailMessage)
            ->subject('💃 ¡Bienvenido a Cubania! Ya estás inscrito')
            ->markdown('mail.enrollments.welcome', [
                'student' => $this->enrollment->student,
                'courseLabel' => CourseSummary::label($course),
                'schedule' => CourseSummary::schedule($course->scheduleSlots),
                'place' => $course->place,
                'teacherName' => $course->teacher?->name,
                'reviewUrl' => route('review-panel'),
            ]);
    }
}
