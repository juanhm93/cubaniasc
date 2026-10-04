<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Enrollment;
use App\Support\CourseSummary;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Staff alert: a student was formally enrolled in a course.
 *
 * Sent synchronously on purpose (no queue worker in production).
 */
class NewEnrollmentNotification extends Notification
{
    public function __construct(
        public Enrollment $enrollment,
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
        $course = $this->enrollment->course;

        return (new MailMessage)
            ->subject('🎓 Nueva inscripción: '.$this->enrollment->student?->name)
            ->markdown('mail.enrollments.new', [
                'student' => $this->enrollment->student,
                'courseLabel' => CourseSummary::label($course),
                'schedule' => CourseSummary::schedule($course->scheduleSlots),
                'teacherName' => $course->teacher?->name,
                'actorName' => $this->actorName,
                'actionUrl' => route('admin.students.show', $this->enrollment->student_id),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'enrollment_id' => $this->enrollment->id,
            'student_id' => $this->enrollment->student_id,
            'student_name' => $this->enrollment->student?->name,
            'course_id' => $this->enrollment->course_id,
            'course_label' => CourseSummary::label($this->enrollment->course),
            'actor_name' => $this->actorName,
        ];
    }
}
