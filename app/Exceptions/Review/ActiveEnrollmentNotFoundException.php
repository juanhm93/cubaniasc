<?php

declare(strict_types=1);

namespace App\Exceptions\Review;

use RuntimeException;

final class ActiveEnrollmentNotFoundException extends RuntimeException
{
    public static function forStudent(): self
    {
        return new self('No tienes una inscripción activa en un curso de Cubanía.');
    }
}
