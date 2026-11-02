<?php

declare(strict_types=1);

namespace App\Exceptions\Content;

use RuntimeException;
use Throwable;

final class MediaStorageUnavailableException extends RuntimeException
{
    public static function notConfigured(string $message): self
    {
        return new self($message);
    }

    public static function unreachable(string $message, Throwable $previous): self
    {
        return new self($message, previous: $previous);
    }
}
