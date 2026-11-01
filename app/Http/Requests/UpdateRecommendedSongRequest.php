<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\RecommendedSong;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;

class UpdateRecommendedSongRequest extends StoreRecommendedSongRequest
{
    /**
     * A song from before the bucket can be fixed in parts while it stays inactive:
     * only an active song needs a file, since one without it is never recommended.
     */
    protected function audioFileRequirement(): string|RequiredIf
    {
        return Rule::requiredIf(function (): bool {
            $song = $this->route('recommendedSong');

            return $this->boolean('is_active') || ($song instanceof RecommendedSong && $song->hasAudioFile());
        });
    }
}
