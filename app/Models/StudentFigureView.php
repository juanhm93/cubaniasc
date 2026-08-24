<?php

namespace App\Models;

use Database\Factories\StudentFigureViewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historial de figuras que el alumno ha visto o repasado, usado para sugerir las últimas 4 vistas.
 */
#[Fillable(['student_id', 'level_content_id', 'viewed_at'])]
class StudentFigureView extends Model
{
    /** @use HasFactory<StudentFigureViewFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function levelContent(): BelongsTo
    {
        return $this->belongsTo(LevelContent::class);
    }
}
