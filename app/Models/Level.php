<?php

namespace App\Models;

use Database\Factories\LevelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'sort_order'])]
class Level extends Model
{
    /** @use HasFactory<LevelFactory> */
    use HasFactory;

    public function levelContents(): HasMany
    {
        return $this->hasMany(LevelContent::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }
}
