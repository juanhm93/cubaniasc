<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Exceptions\Review\ReviewSessionExpiredException;
use App\Models\QuizItem;
use App\Models\QuizOption;
use App\Models\ReviewQuizResponse;
use App\Models\ReviewSession;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

final class QuizGeneratorService
{
    public function __construct(
        private readonly ReviewSessionService $sessionService,
    ) {}

    public function nextQuestion(ReviewSession $session): ?QuizItem
    {
        if ($this->sessionService->hasExpired($session)) {
            return null;
        }

        if ($this->answeredCount($session) >= ReviewSessionService::MAX_QUIZ_QUESTIONS) {
            return null;
        }

        $answeredIds = ReviewQuizResponse::query()
            ->where('review_session_id', $session->id)
            ->pluck('quiz_item_id');

        return $this->basePoolQuery($session)
            ->whereNotIn('id', $answeredIds)
            ->inRandomOrder()
            ->with('options')
            ->first();
    }

    public function recordAnswer(
        ReviewSession $session,
        QuizItem $quizItem,
        QuizOption $quizOption,
    ): ReviewQuizResponse {
        if ($this->sessionService->hasExpired($session)) {
            throw ReviewSessionExpiredException::forSession();
        }

        if ($this->answeredCount($session) >= ReviewSessionService::MAX_QUIZ_QUESTIONS) {
            throw new InvalidArgumentException('This review session has already answered the maximum number of quiz questions.');
        }

        if ($quizOption->quiz_item_id !== $quizItem->id) {
            throw new InvalidArgumentException('The selected option does not belong to this quiz item.');
        }

        return ReviewQuizResponse::query()->create([
            'review_session_id' => $session->id,
            'quiz_item_id' => $quizItem->id,
            'quiz_option_id' => $quizOption->id,
            'is_correct' => $quizOption->is_correct,
            'answered_at' => now(),
        ]);
    }

    public function answeredCount(ReviewSession $session): int
    {
        return ReviewQuizResponse::query()
            ->where('review_session_id', $session->id)
            ->count();
    }

    /**
     * @return Builder<QuizItem>
     */
    private function basePoolQuery(ReviewSession $session): Builder
    {
        return QuizItem::query()
            ->where('is_active', true)
            ->where(function ($query) use ($session): void {
                $query->where('level_id', $session->level_id)
                    ->orWhereNull('level_id');
            });
    }
}
