<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfUserPending
{
    /**
     * Redirect users with pending approval away from the application until an administrator activates their account.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->status !== 'pending') {
            return $next($request);
        }

        if ($this->requestIsExempt($request)) {
            return $next($request);
        }

        return redirect()->route('account.pending');
    }

    private function requestIsExempt(Request $request): bool
    {
        return $request->routeIs([
            'account.pending',
            'logout',
            'verification.notice',
            'verification.verify',
            'verification.send',
        ]);
    }
}
