<?php

declare(strict_types=1);

namespace App\Support;

use Laravel\Fortify\Features;

final class AuthAccess
{
    public static function canRegister(): bool
    {
        return (bool) config('fortify.registration_enabled')
            && Features::enabled(Features::registration());
    }

    public static function canLogin(): bool
    {
        return (bool) config('fortify.login_visible');
    }
}
