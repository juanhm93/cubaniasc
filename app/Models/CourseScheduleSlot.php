<?php

namespace App\Models;

use Database\Factories\CourseScheduleSlotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseScheduleSlot extends Model
{
    /** @use HasFactory<CourseScheduleSlotFactory> */
    use HasFactory;

    protected $fillable = [
        'course_id',
        'weekday',
        'starts_at',
        'ends_at',
        'sort_order',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
