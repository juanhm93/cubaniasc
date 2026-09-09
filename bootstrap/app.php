<?php

use App\Http\Middleware\EnsureAbility;
use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\EnsureOwnerUser;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectIfRegistrationDisabled;
use App\Http\Middleware\RedirectIfUserPending;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        $middleware->alias([
            'active' => RedirectIfUserPending::class,
            'admin' => EnsureAdminRole::class,
            'ability' => EnsureAbility::class,
            'owner' => EnsureOwnerUser::class,
        ]);

        $middleware->web(append: [
            RedirectIfRegistrationDisabled::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
