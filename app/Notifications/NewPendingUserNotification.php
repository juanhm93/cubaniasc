<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Support\NotificationFormat;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Owner/admin alert: someone registered and is waiting for approval.
 *
 * Sent synchronously on purpose (no queue worker in production).
 */
class NewPendingUserNotification extends Notification
{
    public function __construct(public User $user) {}

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
            ->subject('👤 Cuenta pendiente de aprobación: '.$this->user->name)
            ->markdown('mail.users.pending', [
                'user' => $this->user,
                'registeredAt' => NotificationFormat::localDateTime($this->user->created_at),
                'actionUrl' => route('admin.users.index'),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'user_email' => $this->user->email,
        ];
    }
}
