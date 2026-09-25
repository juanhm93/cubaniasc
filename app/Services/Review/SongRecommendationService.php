<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Models\Level;
use App\Models\RecommendedSong;
use App\Models\ReviewSession;
use Illuminate\Support\Collection;

final class SongRecommendationService
{
    public function __construct(
        private readonly StudentLevelResolver $levelResolver,
    ) {}

    /**
     * Active songs of the level; when there are not enough, fills with songs of the
     * earlier levels of the same dance type (closest first).
     *
     * @return Collection<int, RecommendedSong>
     */
    public function recommendForLevel(Level $level, int $count = 3): Collection
    {
        $songs = collect();

        foreach ($this->levelResolver->reviewLevelsFor($level) as $reviewLevel) {
            if ($songs->count() >= $count) {
                break;
            }

            $songs = $songs->concat(
                RecommendedSong::query()
                    ->where('is_active', true)
                    ->whereHas('levels', fn ($query) => $query->whereKey($reviewLevel->id))
                    ->whereNotIn('id', $songs->pluck('id')->all())
                    ->inRandomOrder()
                    ->limit($count - $songs->count())
                    ->get()
            );
        }

        return $songs->values();
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
