<?php

declare(strict_types=1);

namespace App\Exceptions\Review;

use RuntimeException;

final class ReviewDailyLimitException extends RuntimeException
{
    public static function alreadyUsedToday(): self
    {
        return new self('Ya usaste tu repaso de hoy. Vuelve mañana.');
    }
}
