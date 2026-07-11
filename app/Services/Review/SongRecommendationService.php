<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Models\Level;
use App\Models\RecommendedSong;
use App\Models\ReviewSession;
use Illuminate\Support\Collection;

final class SongRecommendationService
{
    /**
     * @return Collection<int, RecommendedSong>
     */
    public function recommendForLevel(Level $level, int $count = 3): Collection
    {
        return RecommendedSong::query()
            ->where('is_active', true)
            ->whereHas('levels', fn ($query) => $query->whereKey($level->id))
            ->inRandomOrder()
            ->limit($count)
            ->get();
    }

    /**
     * @return Collection<int, RecommendedSong>
     */
    public function assignToSession(ReviewSession $session, int $count = 3): Collection
    {
        $session->loadMissing('level');

        $songs = $this->recommendForLevel($session->level, $count);

        if ($songs->isNotEmpty()) {
            $session->songs()->syncWithoutDetaching($songs->pluck('id')->all());
        }

        return $songs;
    }
}
