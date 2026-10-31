<?php

declare(strict_types=1);

namespace App\Actions\FigureVideos;

use App\Models\LevelContent;
use App\Services\Content\FigureVideoStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Confirms an MP4 the browser uploaded straight to the bucket and makes it the
 * figure's video.
 *
 * The presigned PUT cannot limit size or type, so both are checked on the stored
 * object (declared content type and the file's own MP4 header); a rejected object
 * is deleted. {@see self::assign()} then points the figure to the object.
 */
final class AttachFigureVideo
{
    public function __construct(private readonly FigureVideoStorage $storage) {}

    public function execute(LevelContent $content, string $key, ?int $durationSeconds): LevelContent
    {
        $object = $this->storage->find($key);

        if ($object === null) {
            throw ValidationException::withMessages([
                'key' => 'No encontramos el video subido. Vuelve a subirlo.',
            ]);
        }

        if ($object['size_bytes'] > $this->storage->maxUploadBytes()) {
            $this->storage->delete($key);

            throw ValidationException::withMessages([
                'key' => 'El video no puede pesar más de '.intdiv($this->storage->maxUploadBytes(), 1024 * 1024).' MB.',
            ]);
        }

        if ($object['content_type'] !== FigureVideoStorage::CONTENT_TYPE || ! $object['is_mp4']) {
            $this->storage->delete($key);

            throw ValidationException::withMessages([
                'key' => 'El archivo debe ser un video MP4.',
            ]);
        }

        return $this->assign($content, $key, $object['size_bytes'], $durationSeconds);
    }

    /**
     * Makes a checked object the figure's video. The version goes up so clients know
     * their copy is stale, and the replaced object is deleted once the row points to
     * the new one (the storage only deletes the app's own uploads). The same key with
     * the same size changes nothing; a different size means the file was overwritten
     * in the bucket, so the row is brought up to date.
     */
    public function assign(LevelContent $content, string $key, int $sizeBytes, ?int $durationSeconds): LevelContent
    {
        $previousKey = DB::transaction(function () use ($content, $key, $sizeBytes, $durationSeconds): ?string {
            $locked = LevelContent::query()->whereKey($content->id)->lockForUpdate()->firstOrFail();
            $previousKey = $locked->video_file_path;

            if ($previousKey === $key && $locked->video_file_size_bytes === $sizeBytes) {
                return null;
            }

            $locked->forceFill([
                'video_file_path' => $key,
                'video_file_size_bytes' => $sizeBytes,
                'video_file_duration_seconds' => $durationSeconds,
                'video_file_version' => $locked->video_file_version + 1,
                'video_file_uploaded_at' => now(),
            ])->save();

            return $previousKey === $key ? null : $previousKey;
        });

        $this->storage->delete($previousKey);

        return $content->fresh();
    }
}
