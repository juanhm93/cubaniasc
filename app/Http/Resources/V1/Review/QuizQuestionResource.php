<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Review;

use App\Models\QuizItem;
use App\Models\QuizOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A quiz question as the student sees it: the correct option is not revealed.
 *
 * @mixin QuizItem
 */
class QuizQuestionResource extends JsonResource
{
    /**
     * @return array{id: int, type: string, prompt: string, options: list<array{id: int, description: string}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'prompt' => $this->prompt,
            'options' => $this->options
                ->map(fn (QuizOption $option): array => [
                    'id' => $option->id,
                    'description' => $option->description,
                ])
                ->values()
                ->all(),
        ];
    }
}
