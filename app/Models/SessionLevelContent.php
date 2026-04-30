<?php

namespace App\Models;

use Database\Factories\SessionLevelContentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['course_session_id', 'level_content_id', 'notes'])]
class SessionLevelContent extends Model
{
    /** @use HasFactory<SessionLevelContentFactory> */
    use HasFactory;

    public function courseSession(): BelongsTo
    {
        return $this->belongsTo(CourseSession::class);
    }

    public function levelContent(): BelongsTo
    {
        return $this->belongsTo(LevelContent::class);
    }
}
