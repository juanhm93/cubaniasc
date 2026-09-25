<?php

declare(strict_types=1);

namespace App\Http\Resources\Review;

use App\Models\ReviewSession;
use App\Services\Review\ReviewSessionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ReviewSession */
class ReviewSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $sessionService = app(ReviewSessionService::class);

        return [
            'id' => $this->id,
            'level_id' => $this->level_id,
            'started_at' => $this->started_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'completed' => $this->completed,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'duration_seconds' => (int) $this->started_at?->diffInSeconds($this->expires_at),
            'remaining_seconds' => $sessionService->remainingSeconds($this->resource),
            'expired' => $sessionService->hasExpired($this->resource),
            'quiz_max' => ReviewSessionService::MAX_QUIZ_QUESTIONS,
            'quiz_answered_count' => $this->whenCounted('quizResponses'),
            'selected_figures' => LevelContentResource::collection($this->whenLoaded('selectedFigures')),
            'level' => LevelResource::make($this->whenLoaded('level')),
        ];
    }
}
