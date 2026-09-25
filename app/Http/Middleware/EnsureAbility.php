<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\PlatformAbility;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAbility
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $platformAbility = PlatformAbility::tryFrom($ability);
        $user = $request->user();

        if ($platformAbility === null || $user === null || ! $user->hasAbility($platformAbility)) {
            throw new AuthorizationException('You are not authorized to access this section.');
        }

        return $next($request);
    }
}
