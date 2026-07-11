<?php

namespace App\Models;

use Database\Factories\RecommendedSongFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Catálogo de canciones recomendadas para el repaso, asociadas a uno o más niveles.
 */
#[Fillable(['title', 'artist', 'audio_or_link_url', 'is_active'])]
class RecommendedSong extends Model
{
    /** @use HasFactory<RecommendedSongFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function levels(): BelongsToMany
    {
        return $this->belongsToMany(Level::class, 'level_recommended_song');
    }

    public function reviewSessions(): BelongsToMany
    {
        return $this->belongsToMany(ReviewSession::class, 'review_session_songs');
    }
}
