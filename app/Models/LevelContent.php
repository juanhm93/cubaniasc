<?php

namespace App\Models;

use Database\Factories\LevelContentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['level_id', 'name', 'description', 'sort_order'])]
class LevelContent extends Model
{
    /** @use HasFactory<LevelContentFactory> */
    use HasFactory;

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function sessionLevelContents(): HasMany
    {
        return $this->hasMany(SessionLevelContent::class);
    }
}
