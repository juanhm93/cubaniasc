<?php

declare(strict_types=1);

namespace App\Actions\Songs;

use App\Models\RecommendedSong;
use App\Services\Content\SongAudioStorage;

/**
 * The files of the music folder, each with the song of the catalog already using it.
 * One file is one song (its levels live in the pivot), so the panel uses this to
 * avoid offering a file twice.
 */
final class ListSongLibrary
{
    public function __construct(private readonly SongAudioStorage $storage) {}

    /**
     * @return array{folder: string, files: list<array{key: string, name: string, size_bytes: int, song: array{id: int, title: string}|null}>}
     */
    public function execute(): array
    {
        $files = $this->storage->browse();

        $songsByKey = RecommendedSong::query()
            ->whereIn('audio_file_path', array_column($files, 'key'))
            ->get(['id', 'title', 'audio_file_path'])
            ->keyBy('audio_file_path');

        return [
            'folder' => $this->storage->folder(),
            'files' => array_map(function (array $file) use ($songsByKey): array {
                $song = $songsByKey->get($file['key']);

                return $file + [
                    'song' => $song === null ? null : ['id' => $song->id, 'title' => $song->title],
                ];
            }, $files),
        ];
    }
}
