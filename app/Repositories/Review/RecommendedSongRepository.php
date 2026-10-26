<?php

declare(strict_types=1);

namespace App\Repositories\Review;

use App\Models\Level;
use App\Models\RecommendedSong;
use App\Models\ReviewSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Queries on the song catalog (`recommended_songs`, pivot `level_recommended_song`) and
 * the songs assigned to a session (`review_session_songs`).
 */
final class RecommendedSongRepository
{
    /**
     * Random active songs linked to the level.
     *
     * @param  list<int>  $excludeIds
     * @return Collection<int, RecommendedSong>
     */
    public function randomActiveForLevel(Level $level, int $limit, array $excludeIds = []): Collection
    {
        if ($limit <= 0) {
            return new Collection;
        }

        return RecommendedSong::query()
            ->where('is_active', true)
            ->whereHas('levels', fn (Builder $query) => $query->whereKey($level->id))
            ->when($excludeIds !== [], fn (Builder $query) => $query->whereNotIn('id', $excludeIds))
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, RecommendedSong>
     */
    public function forSession(ReviewSession $session): Collection
    {
        return $session->songs()->get();
    }

    public function sessionHasSongs(ReviewSession $session): bool
    {
        return $session->songs()->exists();
    }

    /**
     * @param  list<int>  $songIds
     */
    public function assignToSession(ReviewSession $session, array $songIds): void
    {
        if ($songIds === []) {
            return;
        }

        $session->songs()->syncWithoutDetaching($songIds);
    }
}
