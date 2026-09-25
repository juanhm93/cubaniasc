<?php

declare(strict_types=1);

namespace App\Exceptions\Review;

use RuntimeException;

final class StudentNotIdentifiableException extends RuntimeException
{
    /**
     * Same message whether the student does not exist or has no active enrollment,
     * so the endpoint does not reveal who is enrolled.
     */
    public static function notFound(): self
    {
        return new self('No encontramos un alumno con inscripción activa para ese correo o cédula.');
    }

    public static function notEnrolled(): self
    {
        return self::notFound();
    }

    public static function missingIdentifier(): self
    {
        return new self('Ingresa tu correo o tu cédula para identificarte.');
    }
}
