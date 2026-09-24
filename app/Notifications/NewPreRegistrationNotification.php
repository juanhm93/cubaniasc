<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\PreRegistration;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent synchronously on purpose: the production host has no queue worker, so a
 * queued notification would sit in the jobs table forever.
 */
class NewPreRegistrationNotification extends Notification
{
    public function __construct(public PreRegistration $preRegistration) {}

    /**
     * Database first, mail second, and the order matters: channels run in this
     * sequence, so writing the row first keeps the notification bell working
     * even when the mail server is unreachable.
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
            ->subject('🎉 ¡Nueva preinscripción: '.$this->preRegistration->name.'!')
            ->markdown('mail.pre-registrations.new', [
                'preRegistration' => $this->preRegistration,
                'countryLabel' => $this->countryLabel(),
                'actionUrl' => $this->actionUrl(),
            ]);
    }

    /**
     * Payload stored in the notifications table and rendered by the bell.
     *
     * The target URL is deliberately left out so old rows never freeze a route
     * that may change; the frontend builds it from the pre-registration id.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'pre_registration_id' => $this->preRegistration->id,
            'name' => $this->preRegistration->name,
            'email' => $this->preRegistration->email,
            'phone' => $this->preRegistration->phone,
            'country' => $this->preRegistration->country?->value,
            'country_label' => $this->countryLabel(),
            'submitted_at' => $this->preRegistration->created_at?->toIso8601String(),
        ];
    }

    private function countryLabel(): ?string
    {
        return $this->preRegistration->country?->label();
    }

    private function actionUrl(): string
    {
        return route('admin.payments.index', [
            'tab' => 'mas',
            'preRegistration' => $this->preRegistration->id,
        ]);
    }
}
