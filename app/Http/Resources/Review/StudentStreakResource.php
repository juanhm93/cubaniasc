<?php

declare(strict_types=1);

namespace App\Http\Resources\Review;

use App\Models\StudentStreak;
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
        return [
            'current_streak' => $this->current_streak,
            'last_review_at' => $this->last_review_at?->toIso8601String(),
        ];
    }
}
