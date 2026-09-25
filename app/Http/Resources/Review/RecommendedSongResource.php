<?php

declare(strict_types=1);

namespace App\Http\Resources\Review;

use App\Models\RecommendedSong;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RecommendedSong */
class RecommendedSongResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'artist' => $this->artist,
            'audio_or_link_url' => $this->audio_or_link_url,
        ];
    }
}
