<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Symfony\Component\HttpFoundation\Response;

/**
 * The owner and admins can change roles, payments and the database, so a guessed
 * password must not be enough: they have to turn on two-factor authentication
 * before using the panel. Until then only the security page is reachable.
 */
class EnsureAdminTwoFactorEnabled
{
    /**
     * Routes needed to set up 2FA (Fortify's own two-factor routes live outside the panel groups).
     *
     * @var list<string>
     */
    private const EXEMPT_ROUTES = [
        'security.edit',
        'user-password.update',
    ];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $this->mustEnableTwoFactor($user) || $request->routeIs(self::EXEMPT_ROUTES)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Activa la verificación en dos pasos para continuar.');
        }

        Inertia::flash('toast', [
            'type' => 'error',
            'message' => 'Activa la verificación en dos pasos para seguir usando el panel.',
        ]);

        return redirect()->route('security.edit');
    }

    private function mustEnableTwoFactor(User $user): bool
    {
        if (! config('fortify.require_admin_two_factor') || ! Features::canManageTwoFactorAuthentication()) {
            return false;
        }

        if (! $user->isOwner() && ! $user->isAdmin()) {
            return false;
        }

        return ! $user->hasEnabledTwoFactorAuthentication();
    }
}
