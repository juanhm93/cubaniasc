<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Review;

use App\Models\RecommendedSong;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RecommendedSong */
class SongResource extends JsonResource
{
    /**
     * @return array{id: int, title: string, artist: string, audio_or_link_url: string}
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
