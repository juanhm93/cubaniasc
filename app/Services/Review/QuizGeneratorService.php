<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Exceptions\Review\QuizAnswerRejectedException;
use App\Exceptions\Review\ReviewSessionExpiredException;
use App\Models\QuizItem;
use App\Models\QuizOption;
use App\Models\ReviewQuizResponse;
use App\Models\ReviewSession;
use App\Repositories\Review\QuizRepository;
use InvalidArgumentException;

final class QuizGeneratorService
{
    public function __construct(
        private readonly ReviewSessionService $sessionService,
        private readonly StudentLevelResolver $levelResolver,
        private readonly QuizRepository $quiz,
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

        return $this->quiz->nextUnansweredItem($session, $this->reviewLevelIds($session), $session->level_id);
    }

    public function belongsToSessionPool(ReviewSession $session, QuizItem $quizItem): bool
    {
        return $this->quiz->isInPool($quizItem, $this->reviewLevelIds($session));
    }

    /**
     * The option of the item with the given id, or null when it belongs to another item.
     */
    public function findOption(QuizItem $quizItem, int $optionId): ?QuizOption
    {
        return $this->quiz->findOptionOfItem($quizItem, $optionId);
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

        if ($this->quiz->hasAnswered($session, $quizItem)) {
            throw QuizAnswerRejectedException::alreadyAnswered();
        }

        if ($this->answeredCount($session) >= ReviewSessionService::MAX_QUIZ_QUESTIONS) {
            throw QuizAnswerRejectedException::limitReached(ReviewSessionService::MAX_QUIZ_QUESTIONS);
        }

        return $this->quiz->recordAnswer($session, $quizItem, $quizOption, now());
    }

    public function correctOptionId(QuizItem $quizItem): ?int
    {
        return $this->quiz->correctOptionId($quizItem);
    }

    public function answeredCount(ReviewSession $session): int
    {
        return $this->quiz->answeredCount($session);
    }

    /**
     * @return list<int>
     */
    private function reviewLevelIds(ReviewSession $session): array
    {
        $session->loadMissing('level');

        return $this->levelResolver->reviewLevelIdsFor($session->level);
    }
}
