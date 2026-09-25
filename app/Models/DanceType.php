<?php

namespace App\Models;

use Closure;
use Database\Factories\DanceTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Counts shown on content cards, including the gaps highlighted by the
     * content help mode. Usable with both `withCount()` and `loadCount()`.
     *
     * @return array<int|string, string|Closure(Builder<Model>): mixed>
     */
    public static function contentCounts(): array
    {
        return [
            'levels',
            'levelContents as figures_count',
            'levels as empty_levels_count' => fn (Builder $query) => $query->doesntHave('levelContents'),
            'levelContents as figures_without_video_count' => fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->whereNull('level_contents.video_url')->orWhere('level_contents.video_url', ''),
            ),
            'levelContents as figures_without_description_count' => fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->whereNull('level_contents.description')->orWhere('level_contents.description', ''),
            ),
        ];
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
