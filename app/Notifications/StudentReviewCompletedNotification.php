<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ReviewSession;
use App\Models\Student;
use Illuminate\Notifications\Notification;

/**
 * Bell-only staff alert: a student finished the daily review. No email on
 * purpose, it happens every day for every active student.
 */
class StudentReviewCompletedNotification extends Notification
{
    public function __construct(
        public Student $student,
        public ReviewSession $session,
        public int $streak,
        public ?int $courseId = null,
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
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'review_session_id' => $this->session->id,
            'level_name' => $this->session->level?->name,
            'course_id' => $this->courseId,
            'streak' => $this->streak,
        ];
    }
}
