<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Course;
use App\Models\Level;
use App\Support\CourseSummary;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Celebration email for every active student of a course that levelled up.
 */
class CourseLevelUpNotification extends Notification
{
    public function __construct(
        public Course $course,
        public Level $previousLevel,
        public Level $newLevel,
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
        return (new MailMessage)
            ->subject('🚀 ¡Subieron de nivel! Bienvenidos a '.$this->newLevel->name)
            ->markdown('mail.courses.level-up', [
                'studentName' => $notifiable->name ?? null,
                'courseLabel' => CourseSummary::label($this->course),
                'previousLevelName' => $this->previousLevel->name,
                'newLevelName' => $this->newLevel->name,
                'reviewUrl' => route('review-panel'),
            ]);
    }
}
