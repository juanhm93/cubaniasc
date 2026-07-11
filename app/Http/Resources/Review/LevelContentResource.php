<?php

declare(strict_types=1);

namespace App\Http\Resources\Review;

use App\Models\LevelContent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LevelContent */
class LevelContentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'video_url' => $this->video_url,
            'sort_order' => $this->sort_order,
        ];
    }
}
