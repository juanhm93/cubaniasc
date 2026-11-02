<?php

declare(strict_types=1);

namespace App\Actions\Songs;

use App\Exceptions\Content\MediaStorageUnavailableException;
use App\Models\RecommendedSong;
use App\Services\Content\SongAudioStorage;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a song of the catalog from the panel's form.
 *
 * A new file is checked by size and by its own first bytes, and the version goes up
 * so the app knows its copy is stale. The file is never touched: it stays where it
 * is, and keeping the same file does not reach the bucket at all.
 */
final class SaveRecommendedSong
{
    public function __construct(private readonly SongAudioStorage $storage) {}

    /**
     * @param  array{title: string, artist: string, is_active: bool, level_ids: list<int>, audio_file_path?: string|null, duration_seconds?: int|null}  $data
     */
    public function execute(RecommendedSong $song, array $data): RecommendedSong
    {
        $key = $data['audio_file_path'] ?? null;
        $sizeBytes = $key !== null && $key !== $song->audio_file_path ? $this->checkedSize($key) : null;

        DB::transaction(function () use ($song, $data, $key, $sizeBytes): void {
            $song->fill(Arr::only($data, ['title', 'artist', 'is_active']));

            if ($sizeBytes !== null) {
                $song->forceFill([
                    'audio_file_path' => $key,
                    'audio_file_size_bytes' => $sizeBytes,
                    'audio_file_duration_seconds' => $data['duration_seconds'] ?? null,
                    'audio_file_version' => (int) $song->audio_file_version + 1,
                ]);
            }

            $song->save();
            $song->levels()->sync($data['level_ids']);
        });

        return $song;
    }

    /**
     * This form is an Inertia visit, so a bucket failure comes back as a field error
     * and not as the 503 of the JSON routes.
     */
    private function checkedSize(string $key): int
    {
        try {
            $object = $this->storage->inspect($key);
        } catch (MediaStorageUnavailableException $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'audio_file_path' => 'No pudimos comprobar el archivo. Inténtalo de nuevo.',
            ]);
        }

        if ($object === null) {
            throw ValidationException::withMessages([
                'audio_file_path' => 'No encontramos ese archivo en el bucket.',
            ]);
        }

        if (! $object['is_audio']) {
            throw ValidationException::withMessages([
                'audio_file_path' => 'El archivo debe ser un audio MP3 o WAV.',
            ]);
        }

        return $object['size_bytes'];
    }
}
