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
            'remaining_seconds' => $sessionService->remainingSeconds($this->resource),
            'expired' => $sessionService->hasExpired($this->resource),
            'level' => LevelResource::make($this->whenLoaded('level')),
        ];
    }
}
