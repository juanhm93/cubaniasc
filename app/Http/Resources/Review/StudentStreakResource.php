<?php

declare(strict_types=1);

namespace App\Http\Resources\Review;

use App\Models\StudentStreak;
use App\Services\Review\ReviewSessionService;
use App\Services\Review\StreakService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StudentStreak */
class StudentStreakResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $streakService = app(StreakService::class);

        return [
            'current_streak' => $streakService->currentStreak($this->resource),
            'last_review_at' => $this->last_review_at?->toIso8601String(),
            'recent_days' => $streakService->recentDays($this->student),
            'next_day_starts_at' => ReviewSessionService::localDayBounds()[1]->addSecond()->startOfSecond()->toIso8601String(),
        ];
    }
}
