<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\CourseScheduleSlot;
use App\Models\Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Welcomes a student to the course they were just enrolled in.
 *
 * Sent synchronously on purpose: the production host has no queue worker.
 */
class EnrollmentWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Weekday labels for course_schedule_slots (1 = Monday … 7 = Sunday).
     */
    private const WEEKDAY_LABELS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    public function __construct(public Enrollment $enrollment)
    {
        $this->enrollment->loadMissing([
            'student',
            'course.level.danceType',
            'course.scheduleSlots',
            'course.place',
            'course.teacher',
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '¡Felicidades, ya estás inscrito en Cubanía!',
        );
    }

    public function content(): Content
    {
        $course = $this->enrollment->course;

        return new Content(
            markdown: 'mail.enrollments.welcome',
            with: [
                'studentName' => $this->enrollment->student->name,
                'danceTypeName' => $course->level?->danceType?->name,
                'levelName' => $course->level?->name,
                'schedule' => $course->scheduleSlots
                    ->map(fn (CourseScheduleSlot $slot): string => $this->formatSlot($slot))
                    ->values()
                    ->all(),
                'placeName' => $course->place?->name,
                'placeAddress' => $course->place?->address,
                'teacherName' => $course->teacher?->name,
                'reviewUrl' => route('review-panel'),
            ],
        );
    }

    private function formatSlot(CourseScheduleSlot $slot): string
    {
        $weekday = self::WEEKDAY_LABELS[(int) $slot->weekday] ?? 'Día '.$slot->weekday;

        return sprintf(
            '%s de %s a %s',
            $weekday,
            substr((string) $slot->starts_at, 0, 5),
            substr((string) $slot->ends_at, 0, 5),
        );
    }
}
