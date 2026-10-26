<?php

declare(strict_types=1);

namespace App\Actions\Review;

use App\Exceptions\Review\ReviewSessionNotCompletableException;
use App\Models\ReviewSession;
use App\Models\Student;
use App\Models\StudentStreak;
use App\Services\Review\FigureSelectionService;
use App\Services\Review\ReviewSessionService;
use App\Services\Review\StreakService;

/**
 * Finishes a review session and counts the day in the student's streak.
 *
 * Finishing is allowed after the timer runs out (the time is a window, not a hard stop),
 * but only once the student chose the figures to review, unless the catalog had none to
 * offer. Finishing twice is harmless: the streak only grows the first time.
 */
final class CompleteReviewSession
{
    public function __construct(
        private readonly ReviewSessionService $sessionService,
        private readonly FigureSelectionService $figureSelectionService,
        private readonly StreakService $streakService,
    ) {}

    /**
     * @return array{session: ReviewSession, streak: StudentStreak}
     */
    public function execute(ReviewSession $session, Student $student): array
    {
        if (! $this->figureSelectionService->canComplete($session)) {
            throw ReviewSessionNotCompletableException::missingFigures();
        }

        $wasAlreadyCompleted = $session->completed_at !== null;
        $session = $this->sessionService->markCompleted($session);

        $streak = $wasAlreadyCompleted
            ? $this->streakService->getOrCreate($student)
            : $this->streakService->recordCompletion($student);

        return ['session' => $session, 'streak' => $streak];
    }
}
