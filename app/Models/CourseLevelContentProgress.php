<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseLevelContentProgress extends Model
{
    protected $table = 'course_level_content_progress';

    protected $fillable = [
        'course_id',
        'level_content_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function levelContent(): BelongsTo
    {
        return $this->belongsTo(LevelContent::class);
    }
}
