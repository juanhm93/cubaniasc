<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Review;

use App\Models\LevelContent;
use App\Support\YouTubeVideo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LevelContent */
class FigureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'level_id' => $this->level_id,
            'level_name' => $this->level?->name,
            'name' => $this->name,
            'description' => $this->description,
            'video_url' => $this->video_url,
            'youtube_video_id' => $this->video_url === null ? null : YouTubeVideo::idFromUrl($this->video_url),
        ];
    }
}
