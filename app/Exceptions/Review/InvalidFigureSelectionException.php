<?php

declare(strict_types=1);

namespace App\Exceptions\Review;

use RuntimeException;

final class InvalidFigureSelectionException extends RuntimeException
{
    public static function invalidCount(int $expected): self
    {
        return new self("Debes elegir exactamente {$expected} figuras.");
    }

    public static function notAllowed(): self
    {
        return new self('Alguna de las figuras elegidas no está disponible en este repaso.');
    }

    public static function alreadySelected(): self
    {
        return new self('Ya elegiste las figuras de este repaso.');
    }
}
