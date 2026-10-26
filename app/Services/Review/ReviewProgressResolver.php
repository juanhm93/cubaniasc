<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Enums\ReviewStep;
use App\Models\ReviewSession;

/**
 * Works out where a student should continue a session, so every client resumes it the
 * same way: figures → quiz → songs → done.
 */
final class ReviewProgressResolver
{
    public function __construct(
        private readonly ReviewSessionService $sessionService,
        private readonly FigureSelectionService $figureSelectionService,
        private readonly QuizGeneratorService $quizGeneratorService,
        private readonly SongRecommendationService $songRecommendationService,
    ) {}

    /**
     * Songs are picked when the student reaches the music step, and finishing is allowed
     * after the timer runs out, so a session with songs stays on that step even when it
     * expired. Answered questions mean the figures step is behind (it can be skipped when
     * the catalog had no figures to offer).
     */
    public function nextStep(ReviewSession $session): ReviewStep
    {
        if ($session->completed_at !== null) {
            return ReviewStep::Completed;
        }

        if ($this->songRecommendationService->hasReachedSongs($session)) {
            return ReviewStep::Songs;
        }

        if ($this->sessionService->hasExpired($session)) {
            return ReviewStep::Expired;
        }

        $answeredCount = $this->quizGeneratorService->answeredCount($session);

        if ($answeredCount >= ReviewSessionService::MAX_QUIZ_QUESTIONS) {
            return ReviewStep::Songs;
        }

        if ($answeredCount > 0) {
            return ReviewStep::Quiz;
        }

        if (! $this->figureSelectionService->hasSelectedFigures($session)) {
            return ReviewStep::SelectFigures;
        }

        return ReviewStep::ReviewFigures;
    }
}
