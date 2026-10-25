<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent synchronously on purpose: the production host has no queue worker.
 */
class StudentLoginCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Student $student,
        public string $code,
        public int $expiresInMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Tu código para entrar a la app de Cubanía: {$this->code}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.students.login-code',
            with: [
                'student' => $this->student,
                'code' => $this->code,
                'expiresInMinutes' => $this->expiresInMinutes,
            ],
        );
    }
}
