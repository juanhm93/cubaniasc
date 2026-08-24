<?php

declare(strict_types=1);

namespace App\Http\Resources\Review;

use App\Models\QuizOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QuizOption */
class QuizOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
        ];
    }
}
