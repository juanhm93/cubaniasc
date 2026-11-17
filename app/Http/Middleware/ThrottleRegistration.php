<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fortify registers POST /register without a rate limit. Without one, a bot can
 * fill the users table with pending accounts and claim other people's emails.
 */
class ThrottleRegistration
{
    public const MAX_PER_IP_PER_HOUR = 5;

    public const MAX_PER_DAY = 50;

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST') || ! $request->routeIs('register.store')) {
            return $next($request);
        }

        $limits = [
            'register-ip:'.$request->ip() => [self::MAX_PER_IP_PER_HOUR, 3600],
            'register-global' => [self::MAX_PER_DAY, 86400],
        ];

        foreach ($limits as $key => [$maxAttempts]) {
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                abort(429, 'Demasiados registros. Inténtalo más tarde.');
            }
        }

        foreach ($limits as $key => [, $decaySeconds]) {
            RateLimiter::hit($key, $decaySeconds);
        }

        return $next($request);
    }
}
