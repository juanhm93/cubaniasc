<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email for a user whose pending account was just activated.
 */
class AccountApprovedNotification extends Notification
{
    /**
     * Only email: the user was not able to see the bell until now.
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
            ->subject('✅ Tu cuenta en '.config('app.name').' fue aprobada')
            ->markdown('mail.users.approved', [
                'userName' => $notifiable->name ?? null,
                'loginUrl' => route('login'),
            ]);
    }
}
