<?php

namespace App\Models;

use App\Enums\QuizItemType;
use Database\Factories\QuizItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pregunta del quiz interactivo (figura o dato curioso), independiente del catálogo de figuras.
 */
#[Fillable(['type', 'prompt', 'level_id', 'is_active'])]
class QuizItem extends Model
{
    /** @use HasFactory<QuizItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => QuizItemType::class,
            'is_active' => 'boolean',
        ];
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuizOption::class);
    }

    public function quizResponses(): HasMany
    {
        return $this->hasMany(ReviewQuizResponse::class);
    }
}
