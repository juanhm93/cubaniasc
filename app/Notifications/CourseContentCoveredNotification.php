<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Course;
use App\Models\LevelContent;
use App\Support\CourseSummary;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Owner/admin alert: a teacher marked a figure as covered in a course.
 *
 * Sent synchronously on purpose (no queue worker in production).
 */
class CourseContentCoveredNotification extends Notification
{
    public function __construct(
        public Course $course,
        public LevelContent $figure,
        public int $coveredCount,
        public int $totalCount,
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
            ->subject('✅ Figura vista: '.$this->figure->name.' — '.CourseSummary::label($this->course))
            ->markdown('mail.courses.content-covered', [
                'figureName' => $this->figure->name,
                'courseLabel' => CourseSummary::label($this->course),
                'schedule' => CourseSummary::schedule($this->course->scheduleSlots),
                'coveredCount' => $this->coveredCount,
                'totalCount' => $this->totalCount,
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
            'level_content_id' => $this->figure->id,
            'figure_name' => $this->figure->name,
            'covered_count' => $this->coveredCount,
            'total_count' => $this->totalCount,
            'actor_name' => $this->actorName,
        ];
    }
}
