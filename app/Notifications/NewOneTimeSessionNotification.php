<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\OneTimeSession;
use App\Support\NotificationFormat;
use Illuminate\Notifications\Notification;

/**
 * Bell-only staff alert: a workshop, private class or event was scheduled.
 */
class NewOneTimeSessionNotification extends Notification
{
    public function __construct(
        public OneTimeSession $session,
        public ?string $actorName = null,
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
            'one_time_session_id' => $this->session->id,
            'name' => $this->session->name,
            'type' => $this->session->type,
            'type_label' => NotificationFormat::sessionType($this->session->type),
            'starts_at_label' => NotificationFormat::dateTime($this->session->starts_at),
            'teacher_name' => $this->session->teacher?->name,
            'actor_name' => $this->actorName,
        ];
    }
}
