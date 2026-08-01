<?php

use App\Http\Controllers\Api\Review\ReviewAuthController;
use App\Http\Controllers\Api\Review\ReviewQuizController;
use App\Http\Controllers\Api\Review\ReviewSessionController;
use App\Http\Controllers\Api\Review\ReviewStreakController;
use App\Http\Controllers\Api\Review\StudentIdentificationController;
use Illuminate\Support\Facades\Route;

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
