<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Review;

use App\Models\Level;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Level */
class ReviewLevelResource extends JsonResource
{
    /**
     * @return array{id: int, name: string, slug: string, review_duration_seconds: int}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'review_duration_seconds' => (int) $this->review_duration_seconds,
        ];
    }
}
