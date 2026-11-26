<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Review;

use App\Models\ReviewSession;
use App\Services\Review\ReviewProgressResolver;
use App\Services\Review\ReviewSessionService;
use App\Support\ReviewSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Expects the session with its progress loaded ({@see ReviewSessionService::withProgress()}).
 *
 * @mixin ReviewSession
 */
class ReviewSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $sessionService = app(ReviewSessionService::class);
        $settings = ReviewSettings::forSession($this->resource);

        return [
            'id' => $this->id,
            'level_id' => $this->level_id,
            'started_at' => $this->started_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'duration_seconds' => (int) $this->started_at?->diffInSeconds($this->expires_at),
            'remaining_seconds' => $sessionService->remainingSeconds($this->resource),
            'expired' => $sessionService->hasExpired($this->resource),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'next_step' => app(ReviewProgressResolver::class)->nextStep($this->resource)->value,
            'figure_options_count' => $settings->figureOptionsCount,
            'figure_selection_count' => $settings->figureSelectionCount,
            'figure_options_by_level' => $settings->figuresByLevel,
            'quiz_max' => ReviewSessionService::MAX_QUIZ_QUESTIONS,
            'quiz_answered_count' => (int) $this->quiz_responses_count,
            'level' => ReviewLevelResource::make($this->level),
            'selected_figures' => FigureResource::collection($this->selectedFigures),
        ];
    }
}
