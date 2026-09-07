<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\AuthAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfRegistrationDisabled
{
    /**
     * Send guests to the landing page when public registration is turned off.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (AuthAccess::canRegister()) {
            return $next($request);
        }

        if (! $request->routeIs(['register', 'register.store'])) {
            return $next($request);
        }

        return redirect()->route('home');
    }
}
