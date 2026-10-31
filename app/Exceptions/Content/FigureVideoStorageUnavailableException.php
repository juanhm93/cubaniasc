<?php

declare(strict_types=1);

namespace App\Exceptions\Content;

use RuntimeException;
use Throwable;

final class FigureVideoStorageUnavailableException extends RuntimeException
{
    private const MESSAGE = 'El almacenamiento de videos no está disponible.';

    public static function notConfigured(): self
    {
        return new self(self::MESSAGE);
    }

    public static function unreachable(Throwable $previous): self
    {
        return new self(self::MESSAGE, previous: $previous);
    }
}
