<?php

namespace App\Models;

use Database\Factories\ReviewQuizResponseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Respuesta registrada del alumno a una pregunta del quiz durante una sesión de repaso.
 */
#[Fillable(['review_session_id', 'quiz_item_id', 'quiz_option_id', 'is_correct', 'answered_at'])]
class ReviewQuizResponse extends Model
{
    /** @use HasFactory<ReviewQuizResponseFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'answered_at' => 'datetime',
        ];
    }

    public function reviewSession(): BelongsTo
    {
        return $this->belongsTo(ReviewSession::class);
    }

    public function quizItem(): BelongsTo
    {
        return $this->belongsTo(QuizItem::class);
    }

    public function quizOption(): BelongsTo
    {
        return $this->belongsTo(QuizOption::class);
    }
}
