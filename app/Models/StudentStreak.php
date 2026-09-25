<?php

namespace App\Models;

use Database\Factories\StudentStreakFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Racha de días consecutivos en los que el alumno completó una sesión de repaso.
 */
#[Fillable(['student_id', 'current_streak', 'last_review_at'])]
class StudentStreak extends Model
{
    /** @use HasFactory<StudentStreakFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'current_streak' => 'integer',
            'last_review_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
