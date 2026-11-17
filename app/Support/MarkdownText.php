<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Makes user-supplied text safe to print inside a Markdown mail template.
 *
 * Blade's {{ }} escapes HTML but not Markdown, so a name like
 * "[Verify your payment](https://evil.example)" would become a real link in an
 * email sent with the academy's name. Backslash-escaping every Markdown
 * punctuation character makes the text render literally.
 */
final class MarkdownText
{
    private const SPECIAL_CHARACTERS = '\\`*_{}[]()<>#+-.!|~';

    public static function escape(?string $text): string
    {
        if ($text === null) {
            return '';
        }

        return addcslashes($text, self::SPECIAL_CHARACTERS);
    }
}
