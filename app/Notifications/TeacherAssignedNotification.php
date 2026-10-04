<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Course;
use App\Models\OneTimeSession;
use App\Support\CourseSummary;
use App\Support\NotificationFormat;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Teacher alert: they were put in charge of a course or a one-time session.
 *
 * Sent synchronously on purpose (no queue worker in production).
 */
class TeacherAssignedNotification extends Notification
{
    public function __construct(
        public Course|OneTimeSession $assignment,
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
            ->subject('🧑‍🏫 Te asignaron: '.$this->label())
            ->markdown('mail.sessions.teacher-assigned', [
                'teacherName' => $notifiable->name ?? null,
                'kindLabel' => $this->kindLabel(),
                'label' => $this->label(),
                'when' => $this->when(),
                'placeName' => $this->assignment->place?->name,
                'actorName' => $this->actorName,
                'actionUrl' => $this->assignment instanceof Course
                    ? route('admin.courses.show', $this->assignment)
                    : route('admin.one-time-sessions.index'),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'subject_type' => $this->assignment instanceof Course ? 'course' : 'one_time_session',
            'subject_id' => $this->assignment->id,
            'label' => $this->label(),
            'kind_label' => $this->kindLabel(),
            'when' => $this->when(),
            'actor_name' => $this->actorName,
        ];
    }

    private function label(): string
    {
        return $this->assignment instanceof Course
            ? CourseSummary::label($this->assignment)
            : $this->assignment->name;
    }

    private function kindLabel(): string
    {
        return $this->assignment instanceof Course
            ? 'Curso'
            : NotificationFormat::sessionType($this->assignment->type);
    }

    private function when(): string
    {
        return $this->assignment instanceof Course
            ? CourseSummary::schedule($this->assignment->scheduleSlots)
            : NotificationFormat::dateTime($this->assignment->starts_at);
    }
}
