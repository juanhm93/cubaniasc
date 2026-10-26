<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Level;
use Illuminate\Database\Eloquent\Collection;

/**
 * Queries on `levels`.
 */
final class LevelRepository
{
    /**
     * Earlier levels of the same dance type, closest first.
     *
     * @return Collection<int, Level>
     */
    public function previousLevelsOf(Level $level): Collection
    {
        return Level::query()
            ->where('dance_type_id', $level->dance_type_id)
            ->where('sort_order', '<', $level->sort_order)
            ->orderByDesc('sort_order')
            ->get();
    }
}
