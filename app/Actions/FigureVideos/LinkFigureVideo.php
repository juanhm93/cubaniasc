<?php

declare(strict_types=1);

namespace App\Actions\FigureVideos;

use App\Models\LevelContent;
use App\Services\Content\FigureVideoStorage;
use Illuminate\Validation\ValidationException;

/**
 * Makes a file that is already in the academy's folders the figure's video, without
 * moving or copying it.
 *
 * The file is checked by size and by its own MP4 header. Unlike an upload, a
 * rejected file is never deleted: it is not the app's, it just cannot be used.
 */
final class LinkFigureVideo
{
    public function __construct(
        private readonly FigureVideoStorage $storage,
        private readonly AttachFigureVideo $attach,
    ) {}

    public function execute(LevelContent $content, string $key, ?int $durationSeconds): LevelContent
    {
        $object = $this->storage->inspect($key);

        if ($object === null) {
            throw ValidationException::withMessages([
                'key' => 'No encontramos ese video en el bucket.',
            ]);
        }

        if ($object['size_bytes'] > $this->storage->maxUploadBytes()) {
            throw ValidationException::withMessages([
                'key' => 'El video no puede pesar más de '.intdiv($this->storage->maxUploadBytes(), 1024 * 1024).' MB.',
            ]);
        }

        if (! $object['is_mp4']) {
            throw ValidationException::withMessages([
                'key' => 'El archivo debe ser un video MP4.',
            ]);
        }

        return $this->attach->assign($content, $key, $object['size_bytes'], $durationSeconds);
    }
}
