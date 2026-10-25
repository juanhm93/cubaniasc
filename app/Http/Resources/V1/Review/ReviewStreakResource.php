<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Review;

use App\Models\StudentStreak;
use App\Services\Review\ReviewSessionService;
use App\Services\Review\StreakService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StudentStreak */
class ReviewStreakResource extends JsonResource
{
    /**
     * @return array{current_streak: int, last_review_at: string|null, recent_days: list<array{date: string, completed: bool}>, next_day_starts_at: string}
     */
    public function toArray(Request $request): array
    {
        $streakService = app(StreakService::class);

        return [
            'current_streak' => $streakService->currentStreak($this->resource),
            'last_review_at' => $this->last_review_at?->toIso8601String(),
            'recent_days' => $streakService->recentDays($this->student),
            'next_day_starts_at' => ReviewSessionService::nextDayStartsAt()->toIso8601String(),
        ];
    }
}
