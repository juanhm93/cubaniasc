<?php

declare(strict_types=1);

namespace App\Repositories\Review;

use App\Models\QuizItem;
use App\Models\QuizOption;
use App\Models\ReviewQuizResponse;
use App\Models\ReviewSession;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Queries on the quiz pool (`quiz_items`, `quiz_options`) and the answers given in a
 * session (`review_quiz_responses`).
 *
 * The pool of a session is made of the active items of its review levels plus the
 * general items (no level).
 */
final class QuizRepository
{
    /**
     * A random unanswered item of the pool, with its options. Items of the preferred
     * level come first.
     *
     * @param  list<int>  $reviewLevelIds
     */
    public function nextUnansweredItem(ReviewSession $session, array $reviewLevelIds, int $preferredLevelId): ?QuizItem
    {
        $answeredItemIds = ReviewQuizResponse::query()
            ->where('review_session_id', $session->id)
            ->pluck('quiz_item_id');

        return $this->poolQuery($reviewLevelIds)
            ->whereNotIn('id', $answeredItemIds)
            ->orderByRaw('CASE WHEN level_id = ? THEN 0 ELSE 1 END', [$preferredLevelId])
            ->inRandomOrder()
            ->with('options')
            ->first();
    }

    /**
     * @param  list<int>  $reviewLevelIds
     */
    public function isInPool(QuizItem $item, array $reviewLevelIds): bool
    {
        return $this->poolQuery($reviewLevelIds)->whereKey($item->id)->exists();
    }

    public function findOptionOfItem(QuizItem $item, int $optionId): ?QuizOption
    {
        return QuizOption::query()
            ->where('quiz_item_id', $item->id)
            ->find($optionId);
    }

    public function correctOptionId(QuizItem $item): ?int
    {
        $optionId = $item->options()->where('is_correct', true)->value('id');

        return $optionId === null ? null : (int) $optionId;
    }

    public function answeredCount(ReviewSession $session): int
    {
        return ReviewQuizResponse::query()
            ->where('review_session_id', $session->id)
            ->count();
    }

    public function hasAnswered(ReviewSession $session, QuizItem $item): bool
    {
        return ReviewQuizResponse::query()
            ->where('review_session_id', $session->id)
            ->where('quiz_item_id', $item->id)
            ->exists();
    }

    public function recordAnswer(
        ReviewSession $session,
        QuizItem $item,
        QuizOption $option,
        CarbonInterface $answeredAt,
    ): ReviewQuizResponse {
        return ReviewQuizResponse::query()->create([
            'review_session_id' => $session->id,
            'quiz_item_id' => $item->id,
            'quiz_option_id' => $option->id,
            'is_correct' => $option->is_correct,
            'answered_at' => $answeredAt,
        ]);
    }

    /**
     * @param  list<int>  $reviewLevelIds
     * @return Builder<QuizItem>
     */
    private function poolQuery(array $reviewLevelIds): Builder
    {
        return QuizItem::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($reviewLevelIds): void {
                $query->whereIn('level_id', $reviewLevelIds)
                    ->orWhereNull('level_id');
            });
    }
}
