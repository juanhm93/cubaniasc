<?php

namespace App\Models;

use Database\Factories\ReviewSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sesión de repaso de un alumno: registra duración y nivel.
 *
 * `completed` indica que la sesión está cerrada (terminada o vencida);
 * `completed_at` solo se llena cuando el alumno la terminó de verdad.
 */
#[Fillable(['student_id', 'level_id', 'started_at', 'expires_at', 'completed', 'completed_at'])]
class ReviewSession extends Model
{
    /** @use HasFactory<ReviewSessionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'completed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function figures(): BelongsToMany
    {
        return $this->belongsToMany(LevelContent::class, 'review_session_figures')
            ->withPivot('selected_by_student');
    }

    public function selectedFigures(): BelongsToMany
    {
        return $this->figures()->wherePivot('selected_by_student', true);
    }

    public function songs(): BelongsToMany
    {
        return $this->belongsToMany(RecommendedSong::class, 'review_session_songs');
    }

    public function quizResponses(): HasMany
    {
        return $this->hasMany(ReviewQuizResponse::class);
    }
}
