<?php

namespace App\Models;

use Database\Factories\LevelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'description', 'sort_order', 'dance_type_id', 'review_duration_seconds'])]
class Level extends Model
{
    /** @use HasFactory<LevelFactory> */
    use HasFactory, SoftDeletes;

    public function danceType(): BelongsTo
    {
        return $this->belongsTo(DanceType::class);
    }

    public function levelContents(): HasMany
    {
        return $this->hasMany(LevelContent::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function recommendedSongs(): BelongsToMany
    {
        return $this->belongsToMany(RecommendedSong::class, 'level_recommended_song');
    }

    public function reviewSessions(): HasMany
    {
        return $this->hasMany(ReviewSession::class);
    }

    public function quizItems(): HasMany
    {
        return $this->hasMany(QuizItem::class);
    }
}
