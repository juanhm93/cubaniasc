<?php

declare(strict_types=1);

namespace App\Exceptions\Review;

use RuntimeException;

final class ReviewSessionNotCompletableException extends RuntimeException
{
    public static function missingFigures(): self
    {
        return new self('Elige y repasa tus figuras antes de terminar el repaso.');
    }
}
