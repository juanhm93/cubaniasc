<?php

namespace App\Models;

use App\Services\Content\FigureVideoStorage;
use Database\Factories\LevelContentFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A figure of a level. It can have two videos at once: the YouTube link in
 * `video_url` and its own MP4 in the private R2 bucket (`video_file_*`). The
 * `video_file_*` columns are managed by the upload flow, not by the figure form,
 * and the object key is never serialized.
 */
#[Fillable(['level_id', 'name', 'description', 'video_url', 'sort_order'])]
#[Hidden(['video_file_path'])]
#[Appends(['video_file'])]
class LevelContent extends Model
{
    /** @use HasFactory<LevelContentFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'video_file_size_bytes' => 'integer',
            'video_file_duration_seconds' => 'integer',
            'video_file_version' => 'integer',
            'video_file_uploaded_at' => 'datetime',
        ];
    }

    public function hasVideoFile(): bool
    {
        return filled($this->attributes['video_file_path'] ?? null);
    }

    /**
     * The figure's own MP4 as clients see it: no object key and no URL. `linked` says
     * the file lives in the academy's folders instead of the panel's uploads, so
     * removing it only unlinks it.
     *
     * @return Attribute<array{available: bool, linked: bool, size_bytes: int|null, duration_seconds: int|null, version: int, uploaded_at: string|null}, never>
     */
    protected function videoFile(): Attribute
    {
        return Attribute::get(function (): array {
            $available = $this->hasVideoFile();

            return [
                'available' => $available,
                'linked' => $available && ! str_starts_with($this->attributes['video_file_path'], FigureVideoStorage::MANAGED_PREFIX),
                'size_bytes' => $available ? $this->video_file_size_bytes : null,
                'duration_seconds' => $available ? $this->video_file_duration_seconds : null,
                'version' => (int) ($this->video_file_version ?? 0),
                'uploaded_at' => $available && $this->video_file_uploaded_at !== null
                    ? $this->serializeDate($this->video_file_uploaded_at)
                    : null,
            ];
        });
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function sessionLevelContents(): HasMany
    {
        return $this->hasMany(SessionLevelContent::class);
    }

    public function reviewSessions(): BelongsToMany
    {
        return $this->belongsToMany(ReviewSession::class, 'review_session_figures')
            ->withPivot('selected_by_student');
    }

    public function studentFigureViews(): HasMany
    {
        return $this->hasMany(StudentFigureView::class);
    }
}
