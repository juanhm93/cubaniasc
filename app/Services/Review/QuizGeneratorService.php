<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Exceptions\Review\QuizAnswerRejectedException;
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
        private readonly StudentLevelResolver $levelResolver,
    ) {}

    /**
     * Next unanswered question: items of the current level come first, then earlier
     * levels of the same dance type and general items (no level).
     */
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
            ->orderByRaw('CASE WHEN level_id = ? THEN 0 ELSE 1 END', [$session->level_id])
            ->inRandomOrder()
            ->with('options')
            ->first();
    }

    public function belongsToSessionPool(ReviewSession $session, QuizItem $quizItem): bool
    {
        return $this->basePoolQuery($session)->whereKey($quizItem->id)->exists();
    }

    public function recordAnswer(
        ReviewSession $session,
        QuizItem $quizItem,
        QuizOption $quizOption,
    ): ReviewQuizResponse {
        if ($this->sessionService->hasExpired($session)) {
            throw ReviewSessionExpiredException::forSession();
        }

        if ($quizOption->quiz_item_id !== $quizItem->id) {
            throw new InvalidArgumentException('The selected option does not belong to this quiz item.');
        }

        $alreadyAnswered = ReviewQuizResponse::query()
            ->where('review_session_id', $session->id)
            ->where('quiz_item_id', $quizItem->id)
            ->exists();

        if ($alreadyAnswered) {
            throw QuizAnswerRejectedException::alreadyAnswered();
        }

        if ($this->answeredCount($session) >= ReviewSessionService::MAX_QUIZ_QUESTIONS) {
            throw QuizAnswerRejectedException::limitReached(ReviewSessionService::MAX_QUIZ_QUESTIONS);
        }

        return ReviewQuizResponse::query()->create([
            'review_session_id' => $session->id,
            'quiz_item_id' => $quizItem->id,
            'quiz_option_id' => $quizOption->id,
            'is_correct' => $quizOption->is_correct,
            'answered_at' => now(),
        ]);
    }

    public function correctOptionId(QuizItem $quizItem): ?int
    {
        return $quizItem->options()->where('is_correct', true)->value('id');
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
        $session->loadMissing('level');
        $reviewLevelIds = $this->levelResolver->reviewLevelsFor($session->level)->pluck('id')->all();

        return QuizItem::query()
            ->where('is_active', true)
            ->where(function ($query) use ($reviewLevelIds): void {
                $query->whereIn('level_id', $reviewLevelIds)
                    ->orWhereNull('level_id');
            });
    }
}
