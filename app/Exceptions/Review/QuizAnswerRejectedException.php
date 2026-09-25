<?php

declare(strict_types=1);

namespace App\Exceptions\Review;

use RuntimeException;

final class QuizAnswerRejectedException extends RuntimeException
{
    public static function alreadyAnswered(): self
    {
        return new self('Ya respondiste esta pregunta.');
    }

    public static function limitReached(int $maximum): self
    {
        return new self("Ya respondiste las {$maximum} preguntas de este repaso.");
    }
}
