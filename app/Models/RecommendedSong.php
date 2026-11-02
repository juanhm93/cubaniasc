<?php

namespace App\Models;

use App\Actions\Songs\SaveRecommendedSong;
use Database\Factories\RecommendedSongFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catálogo de canciones recomendadas para el repaso, asociadas a uno o más niveles.
 *
 * El audio es un archivo de la carpeta de música del bucket R2 (`audio_file_*`). Esas
 * columnas las maneja {@see SaveRecommendedSong}, no el formulario,
 * y la clave del objeto nunca se serializa. `audio_or_link_url` es el enlace externo
 * de antes: ya no se usa ni se expone.
 */
#[Fillable(['title', 'artist', 'is_active'])]
#[Hidden(['audio_file_path', 'audio_or_link_url'])]
class RecommendedSong extends Model
{
    /** @use HasFactory<RecommendedSongFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'audio_file_size_bytes' => 'integer',
            'audio_file_duration_seconds' => 'integer',
            'audio_file_version' => 'integer',
        ];
    }

    public function hasAudioFile(): bool
    {
        return filled($this->attributes['audio_file_path'] ?? null);
    }

    /**
     * The song's file as clients see it: no object key and no URL.
     *
     * @return Attribute<array{available: bool, format: string|null, size_bytes: int|null, duration_seconds: int|null, version: int}, never>
     */
    protected function audioFile(): Attribute
    {
        return Attribute::get(function (): array {
            $available = $this->hasAudioFile();

            return [
                'available' => $available,
                'format' => $available ? strtolower(pathinfo($this->audio_file_path, PATHINFO_EXTENSION)) : null,
                'size_bytes' => $available ? $this->audio_file_size_bytes : null,
                'duration_seconds' => $available ? $this->audio_file_duration_seconds : null,
                'version' => (int) ($this->audio_file_version ?? 0),
            ];
        });
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
