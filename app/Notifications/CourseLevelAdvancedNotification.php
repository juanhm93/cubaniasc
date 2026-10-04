<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Course;
use App\Models\Level;
use App\Support\CourseSummary;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Staff alert: a course moved on to the next level.
 *
 * Sent synchronously on purpose (no queue worker in production).
 */
class CourseLevelAdvancedNotification extends Notification
{
    public function __construct(
        public Course $course,
        public Level $previousLevel,
        public Level $newLevel,
        public int $studentCount,
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
            ->subject('🚀 Subida de nivel: '.$this->previousLevel->name.' → '.$this->newLevel->name)
            ->markdown('mail.courses.level-advanced', [
                'courseLabel' => CourseSummary::label($this->course),
                'schedule' => CourseSummary::schedule($this->course->scheduleSlots),
                'previousLevelName' => $this->previousLevel->name,
                'newLevelName' => $this->newLevel->name,
                'teacherName' => $this->course->teacher?->name,
                'studentCount' => $this->studentCount,
                'actorName' => $this->actorName,
                'actionUrl' => route('admin.courses.show', $this->course),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'course_id' => $this->course->id,
            'course_label' => CourseSummary::label($this->course),
            'previous_level_name' => $this->previousLevel->name,
            'new_level_name' => $this->newLevel->name,
            'student_count' => $this->studentCount,
            'actor_name' => $this->actorName,
        ];
    }
}
