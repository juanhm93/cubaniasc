<?php

declare(strict_types=1);

namespace App\Exceptions\Review;

use RuntimeException;

final class StudentNotIdentifiableException extends RuntimeException
{
    public static function notFound(): self
    {
        return new self('No student matches the provided email or DNI.');
    }

    public static function notEnrolled(): self
    {
        return new self('The student is not enrolled in an active course.');
    }

    public static function missingIdentifier(): self
    {
        return new self('An email or DNI is required to identify the student.');
    }
}
