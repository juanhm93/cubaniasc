<?php

use App\Http\Controllers\Api\Review\ReviewAuthController;
use App\Http\Controllers\Api\Review\ReviewQuizController;
use App\Http\Controllers\Api\Review\ReviewSessionController;
use App\Http\Controllers\Api\Review\ReviewStreakController;
use App\Http\Controllers\Api\Review\StudentIdentificationController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\RequestLoginCodeController;
use App\Http\Controllers\Api\V1\Auth\VerifyLoginCodeController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\Review\ReviewQuizController as AppReviewQuizController;
use App\Http\Controllers\Api\V1\Review\ReviewSessionController as AppReviewSessionController;
use App\Http\Controllers\Api\V1\Review\ReviewTodayController;
use Illuminate\Support\Facades\Route;

/*
| API v1 for the mobile app. Students sign in with a one-time code emailed to them
| and receive a Sanctum token with the `app` ability. Contract: docs/api/v1/openapi.yaml
*/
Route::prefix('v1')->name('v1.')->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('request-code', RequestLoginCodeController::class)
            ->middleware('throttle:app-login-request')
            ->name('request-code');

        Route::post('verify-code', VerifyLoginCodeController::class)
            ->middleware('throttle:app-login-verify')
            ->name('verify-code');
    });

    Route::middleware(['auth:sanctum', 'app.student'])->group(function (): void {
        Route::get('me', MeController::class)->name('me');
        Route::post('auth/logout', LogoutController::class)->name('auth.logout');

        // Daily review ("repaso"). Shares services and repositories with the web panel below.
        Route::prefix('review')->name('review.')->group(function (): void {
            Route::get('today', ReviewTodayController::class)->name('today');

            Route::post('sessions', [AppReviewSessionController::class, 'store'])->name('sessions.store');
            Route::get('sessions/{session}', [AppReviewSessionController::class, 'show'])->name('sessions.show');
            Route::get('sessions/{session}/figure-options', [AppReviewSessionController::class, 'figureOptions'])
                ->name('sessions.figure-options');
            Route::post('sessions/{session}/figures', [AppReviewSessionController::class, 'storeFigures'])
                ->name('sessions.figures.store');
            Route::post('sessions/{session}/figures/{figure}/view', [AppReviewSessionController::class, 'recordFigureView'])
                ->name('sessions.figures.view');
            Route::get('sessions/{session}/songs', [AppReviewSessionController::class, 'songs'])
                ->name('sessions.songs');
            Route::get('sessions/{session}/quiz/next', [AppReviewQuizController::class, 'next'])
                ->name('sessions.quiz.next');
            Route::post('sessions/{session}/quiz/{item}/answer', [AppReviewQuizController::class, 'answer'])
                ->name('sessions.quiz.answer');
            Route::post('sessions/{session}/complete', [AppReviewSessionController::class, 'complete'])
                ->name('sessions.complete');
        });
    });
});

Route::prefix('review')->name('review.')->group(function (): void {
    Route::post('identify', StudentIdentificationController::class)
        ->middleware('throttle:review-identify')
        ->name('identify');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [ReviewAuthController::class, 'me'])->name('me');
        Route::delete('logout', [ReviewAuthController::class, 'logout'])->name('logout');
        Route::get('streak', ReviewStreakController::class)->name('streak');

        Route::post('sessions', [ReviewSessionController::class, 'store'])->name('sessions.store');
        Route::get('sessions/current', [ReviewSessionController::class, 'current'])->name('sessions.current');
        Route::get('sessions/{session}', [ReviewSessionController::class, 'show'])->name('sessions.show');
        Route::get('sessions/{session}/figure-options', [ReviewSessionController::class, 'figureOptions'])
            ->name('sessions.figure-options');
        Route::post('sessions/{session}/figures', [ReviewSessionController::class, 'storeFigures'])
            ->name('sessions.figures.store');
        Route::post('sessions/{session}/figures/{content}/view', [ReviewSessionController::class, 'recordFigureView'])
            ->name('sessions.figures.view');
        Route::post('sessions/{session}/figures/{content}/video-url', [ReviewSessionController::class, 'figureVideoUrl'])
            ->middleware('throttle:60,1')
            ->name('sessions.figures.video-url');
        Route::get('sessions/{session}/songs', [ReviewSessionController::class, 'songs'])
            ->name('sessions.songs');
        Route::get('sessions/{session}/quiz/next', [ReviewQuizController::class, 'next'])
            ->name('sessions.quiz.next');
        Route::post('sessions/{session}/quiz/{item}/answer', [ReviewQuizController::class, 'answer'])
            ->name('sessions.quiz.answer');
        Route::post('sessions/{session}/complete', [ReviewSessionController::class, 'complete'])
            ->name('sessions.complete');
    });
});
