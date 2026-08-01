<?php

use App\Exceptions\Review\ActiveEnrollmentNotFoundException;
use App\Exceptions\Review\InvalidFigureSelectionException;
use App\Exceptions\Review\ReviewDailyLimitException;
use App\Exceptions\Review\ReviewSessionExpiredException;
use App\Exceptions\Review\StudentNotIdentifiableException;
use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\EnsureOwnerUser;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectIfUserPending;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        $middleware->alias([
            'active' => RedirectIfUserPending::class,
            'admin' => EnsureAdminRole::class,
            'owner' => EnsureOwnerUser::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (StudentNotIdentifiableException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $exception->getMessage()], 404);
            }
        });

        $exceptions->render(function (ActiveEnrollmentNotFoundException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $exception->getMessage()], 404);
            }
        });

        $exceptions->render(function (ReviewSessionExpiredException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $exception->getMessage()], 410);
            }
        });

        $exceptions->render(function (ReviewDailyLimitException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $exception->getMessage()], 409);
            }
        });

        $exceptions->render(function (InvalidFigureSelectionException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }
        });
    })->create();
