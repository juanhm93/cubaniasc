<?php

declare(strict_types=1);

namespace App\Http\Resources\Review;

use App\Models\QuizItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QuizItem */
class QuizItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'prompt' => $this->prompt,
            'options' => QuizOptionResource::collection($this->whenLoaded('options')),
        ];
    }
}
