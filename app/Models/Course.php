<?php

namespace App\Models;

use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['level_id', 'schedule_id', 'price', 'company_id', 'place_id', 'user_id', 'is_active'])]
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Course $course): void {
            CourseLevel::query()->firstOrCreate(
                [
                    'course_id' => $course->id,
                    'level_id' => $course->level_id,
                ],
                ['sort_order' => 1],
            );
        });
    }

    /**
     * @param  Builder<Course>  $query
     * @return Builder<Course>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * Horarios flexibles del curso (día de la semana + franja horaria).
     *
     * @return HasMany<CourseScheduleSlot, $this>
     */
    public function scheduleSlots(): HasMany
    {
        return $this->hasMany(CourseScheduleSlot::class)->orderBy('sort_order')->orderBy('weekday');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function courseSessions(): HasMany
    {
        return $this->hasMany(CourseSession::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Ordered levels this course has gone through (progress / catalog path).
     *
     * @return BelongsToMany<Level, $this>
     */
    public function levelsThrough(): BelongsToMany
    {
        return $this->belongsToMany(Level::class, 'course_levels')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * @return HasMany<CourseLevel, $this>
     */
    public function courseLevels(): HasMany
    {
        return $this->hasMany(CourseLevel::class)->orderBy('sort_order');
    }

    /**
     * Figures marked as covered for this course (whole group), keyed per level content.
     *
     * @return HasMany<CourseLevelContentProgress, $this>
     */
    public function courseLevelContentProgress(): HasMany
    {
        return $this->hasMany(CourseLevelContentProgress::class);
    }
}
