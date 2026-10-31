<?php

declare(strict_types=1);

namespace App\Actions\FigureVideos;

use App\Models\LevelContent;
use App\Services\Content\FigureVideoStorage;

/**
 * One folder of the bucket, with the figures already using each file. A file can
 * back several figures, so this only informs: it does not block anything.
 */
final class ListFigureVideoLibrary
{
    public function __construct(private readonly FigureVideoStorage $storage) {}

    /**
     * @return array{folder: string, folders: list<string>, files: list<array{key: string, name: string, size_bytes: int, linked_to: list<array{id: int, name: string}>}>}
     */
    public function execute(string $folder): array
    {
        $listing = $this->storage->browse($folder);

        $figuresByKey = LevelContent::query()
            ->whereIn('video_file_path', array_column($listing['files'], 'key'))
            ->orderBy('name')
            ->get(['id', 'name', 'video_file_path'])
            ->groupBy('video_file_path');

        return [
            'folder' => $folder,
            'folders' => $listing['folders'],
            'files' => array_map(fn (array $file): array => $file + [
                'linked_to' => ($figuresByKey->get($file['key']) ?? collect())
                    ->map(fn (LevelContent $figure): array => ['id' => $figure->id, 'name' => $figure->name])
                    ->values()
                    ->all(),
            ], $listing['files']),
        ];
    }
}
