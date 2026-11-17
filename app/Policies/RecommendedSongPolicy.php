<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RecommendedSong;
use App\Models\User;

/**
 * Same rule as dance types and levels: teachers manage songs, but only admins
 * and the owner can delete them.
 */
class RecommendedSongPolicy
{
    public function delete(User $user, RecommendedSong $recommendedSong): bool
    {
        return $user->isAdmin() || $user->isOwner();
    }
}
