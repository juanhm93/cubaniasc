<?php

declare(strict_types=1);

namespace App\Exceptions\Review;

use RuntimeException;

final class InvalidFigureSelectionException extends RuntimeException
{
    public static function invalidCount(int $expected): self
    {
        return new self("Exactly {$expected} figures must be selected.");
    }

    public static function notAllowed(): self
    {
        return new self('One or more selected figures are not valid for this session.');
    }
}
