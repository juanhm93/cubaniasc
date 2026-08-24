<?php

namespace App\Models;

use Database\Factories\QuizOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Opción de respuesta de una pregunta del quiz; exactamente una es correcta por pregunta.
 */
#[Fillable(['quiz_item_id', 'description', 'is_correct'])]
class QuizOption extends Model
{
    /** @use HasFactory<QuizOptionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
        ];
    }

    public function quizItem(): BelongsTo
    {
        return $this->belongsTo(QuizItem::class);
    }

    public function quizResponses(): HasMany
    {
        return $this->hasMany(ReviewQuizResponse::class);
    }
}
