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
     * `level_ids` only comes in the library: the session's songs are not grouped.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'artist' => $this->artist,
            'audio' => $this->audio_file,
            'level_ids' => $this->whenLoaded('levels', fn (): array => $this->levels->modelKeys()),
        ];
    }
}
