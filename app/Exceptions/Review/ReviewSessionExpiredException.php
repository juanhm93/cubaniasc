<?php

declare(strict_types=1);

namespace App\Exceptions\Review;

use RuntimeException;

final class ReviewSessionExpiredException extends RuntimeException
{
    public static function forSession(): self
    {
        return new self('The review session has expired.');
    }
}
