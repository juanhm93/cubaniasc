<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Models\Level;
use App\Models\RecommendedSong;
use App\Models\ReviewSession;
use App\Repositories\Review\RecommendedSongRepository;
use Illuminate\Support\Collection;

final class SongRecommendationService
{
    public const SONGS_PER_SESSION = 3;

    public function __construct(
        private readonly StudentLevelResolver $levelResolver,
        private readonly RecommendedSongRepository $songs,
    ) {}

    /**
     * Active songs of the level; when there are not enough, fills with songs of the
     * earlier levels of the same dance type (closest first).
     *
     * @return Collection<int, RecommendedSong>
     */
    public function recommendForLevel(Level $level, int $count = self::SONGS_PER_SESSION): Collection
    {
        $songs = collect();

        foreach ($this->levelResolver->reviewLevelsFor($level) as $reviewLevel) {
            if ($songs->count() >= $count) {
                break;
            }

            $songs = $songs->concat($this->songs->randomActiveForLevel(
                $reviewLevel,
                $count - $songs->count(),
                $songs->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
            ));
        }

        return $songs->values();
    }

    /**
     * @return Collection<int, RecommendedSong>
     */
    public function assignToSession(ReviewSession $session, int $count = self::SONGS_PER_SESSION): Collection
    {
        $session->loadMissing('level');

        $songs = $this->recommendForLevel($session->level, $count);

        $this->songs->assignToSession($session, $songs->pluck('id')->map(fn (mixed $id): int => (int) $id)->all());

        return $songs;
    }

    /**
     * Songs of the session. They are picked the first time they are asked for, so a
     * reload shows the same songs.
     *
     * @return Collection<int, RecommendedSong>
     */
    public function forSession(ReviewSession $session): Collection
    {
        if (! $this->songs->sessionHasSongs($session)) {
            $this->assignToSession($session);
        }

        return $this->songs->forSession($session);
    }

    /**
     * Songs are only picked once the student reaches the music step.
     */
    public function hasReachedSongs(ReviewSession $session): bool
    {
        return $this->songs->sessionHasSongs($session);
    }
}
