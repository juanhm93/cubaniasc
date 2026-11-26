<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Level;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Queries on `levels`.
 */
final class LevelRepository
{
    /**
     * Earlier levels of the same dance type, closest first. With a limit, only the
     * closest `$limit` levels are returned; null returns all of them.
     *
     * @return Collection<int, Level>
     */
    public function previousLevelsOf(Level $level, ?int $limit = null): Collection
    {
        if ($limit === 0) {
            return new Collection;
        }

        return $this->previousLevelsQuery($level)
            ->when($limit !== null, fn (Builder $query): Builder => $query->limit($limit))
            ->get();
    }

    public function countPreviousLevelsOf(Level $level): int
    {
        return $this->previousLevelsQuery($level)->count();
    }

    /**
     * @return Builder<Level>
     */
    private function previousLevelsQuery(Level $level): Builder
    {
        return Level::query()
            ->where('dance_type_id', $level->dance_type_id)
            ->where('sort_order', '<', $level->sort_order)
            ->orderByDesc('sort_order');
    }
}
