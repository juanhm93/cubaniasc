<?php

namespace App\Enums;

enum PreRegistrationCountry: string
{
    case Venezuela = 'VE';
    case Colombia = 'CO';

    public function label(): string
    {
        return match ($this) {
            self::Venezuela => 'Venezuela',
            self::Colombia => 'Colombia',
        };
    }
}
