<?php

namespace App\Enums;

/**
 * What a signed URL to a figure's MP4 is for: saving the file (attachment) or
 * playing it in place (inline).
 */
enum FigureVideoUrlPurpose: string
{
    case Download = 'download';
    case Stream = 'stream';

    public function disposition(): string
    {
        return match ($this) {
            self::Download => 'attachment',
            self::Stream => 'inline',
        };
    }
}
