<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\PreRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent synchronously on purpose: the production host has no queue worker.
 */
class PreRegistrationConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PreRegistration $preRegistration) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '¡Recibimos tu preinscripción en Cubanía!',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.pre-registrations.confirmation',
            with: [
                'preRegistration' => $this->preRegistration,
            ],
        );
    }
}
