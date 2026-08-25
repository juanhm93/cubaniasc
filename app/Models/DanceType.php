<?php

namespace App\Models;

use Database\Factories\DanceTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'description', 'sort_order'])]
class DanceType extends Model
{
    /** @use HasFactory<DanceTypeFactory> */
    use HasFactory, SoftDeletes;

    public function levels(): HasMany
    {
        return $this->hasMany(Level::class);
    }

    public function levelContents(): HasManyThrough
    {
        return $this->hasManyThrough(LevelContent::class, Level::class);
    }

    public function isUsedByCourses(): bool
    {
        $levelIds = $this->levels()->pluck('id');

        if ($levelIds->isEmpty()) {
            return false;
        }

        return Course::query()->whereIn('level_id', $levelIds)->exists()
            || CourseLevel::query()->whereIn('level_id', $levelIds)->exists();
    }
}
