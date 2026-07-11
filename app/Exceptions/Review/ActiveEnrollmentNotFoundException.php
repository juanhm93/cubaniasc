<?php

declare(strict_types=1);

namespace App\Exceptions\Review;

use RuntimeException;

final class ActiveEnrollmentNotFoundException extends RuntimeException
{
    public static function forStudent(): self
    {
        return new self('The student does not have an active enrollment.');
    }
}
