<?php

declare(strict_types=1);

namespace App\Actions\FigureVideos;

use App\Models\LevelContent;
use App\Services\Content\FigureVideoStorage;
use Illuminate\Support\Facades\DB;

/**
 * Removes the figure's own MP4. The YouTube link is not touched.
 *
 * The row is cleared first and the object deleted afterwards, so the row never
 * points to a missing file. The version still goes up, so clients that saved the
 * old file see it was removed.
 */
final class RemoveFigureVideo
{
    public function __construct(private readonly FigureVideoStorage $storage) {}

    public function execute(LevelContent $content): LevelContent
    {
        $previousKey = DB::transaction(function () use ($content): ?string {
            $locked = LevelContent::query()->whereKey($content->id)->lockForUpdate()->firstOrFail();
            $previousKey = $locked->video_file_path;

            if ($previousKey === null) {
                return null;
            }

            $locked->forceFill([
                'video_file_path' => null,
                'video_file_size_bytes' => null,
                'video_file_duration_seconds' => null,
                'video_file_version' => $locked->video_file_version + 1,
                'video_file_uploaded_at' => null,
            ])->save();

            return $previousKey;
        });

        $this->storage->delete($previousKey);

        return $content->fresh();
    }
}
