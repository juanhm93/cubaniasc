<?php

declare(strict_types=1);

namespace App\Actions\FigureVideos;

use App\Models\Level;
use App\Models\LevelContent;
use App\Services\Content\FigureVideoStorage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The files in a level's folder with the figure each one seems to belong to, plus
 * the level's linked videos that no longer match the bucket.
 *
 * Only figures of this level without a video are candidates. Each figure takes its
 * best file, so a variant such as `arco_triple_fluido` is left without a suggestion
 * instead of competing with `arco_triple`. Staff always review before linking.
 */
final class SuggestFigureVideoLinks
{
    /** Below this, names that only share a few letters would start to match. */
    private const MINIMUM_SIMILARITY = 65.0;

    public function __construct(
        private readonly FigureVideoStorage $storage,
        private readonly ListFigureVideoLibrary $library,
    ) {}

    /**
     * @return array{
     *     folder: string,
     *     files: list<array{key: string, name: string, size_bytes: int, linked_to: list<array{id: int, name: string}>, suggested_figure_id: int|null, match: 'exact'|'similar'|null}>,
     *     broken_links: list<array{figure_id: int, name: string, key: string, reason: 'missing'|'size_changed'}>
     * }
     */
    public function execute(Level $level): array
    {
        $listing = $this->library->execute($this->storage->defaultFolderFor($level));
        $figures = $level->levelContents()->orderBy('sort_order')->get();
        $suggestions = $this->suggest($listing['files'], $figures, Str::slug($level->name));

        return [
            'folder' => $listing['folder'],
            'files' => array_map(fn (array $file): array => $file + [
                'suggested_figure_id' => $suggestions[$file['key']]['figure_id'] ?? null,
                'match' => $suggestions[$file['key']]['match'] ?? null,
            ], $listing['files']),
            'broken_links' => $this->brokenLinks($figures),
        ];
    }

    /**
     * @param  list<array{key: string, name: string, size_bytes: int, linked_to: list<array{id: int, name: string}>}>  $files
     * @param  Collection<int, LevelContent>  $figures
     * @return array<string, array{figure_id: int, match: 'exact'|'similar'}>
     */
    private function suggest(array $files, Collection $figures, string $levelSlug): array
    {
        $candidates = [];

        foreach ($files as $file) {
            if ($file['linked_to'] !== []) {
                continue;
            }

            $stem = Str::chopEnd(Str::slug(pathinfo($file['name'], PATHINFO_FILENAME)), '-'.$levelSlug);

            foreach ($figures->reject(fn (LevelContent $figure): bool => $figure->hasVideoFile()) as $figure) {
                similar_text($stem, Str::slug($figure->name), $similarity);

                if ($similarity >= self::MINIMUM_SIMILARITY) {
                    $candidates[] = ['key' => $file['key'], 'figure_id' => $figure->id, 'similarity' => $similarity];
                }
            }
        }

        usort($candidates, fn (array $first, array $second): int => $second['similarity'] <=> $first['similarity']);

        $suggestions = [];
        $takenFigures = [];

        foreach ($candidates as $candidate) {
            if (isset($suggestions[$candidate['key']]) || isset($takenFigures[$candidate['figure_id']])) {
                continue;
            }

            $takenFigures[$candidate['figure_id']] = true;
            $suggestions[$candidate['key']] = [
                'figure_id' => $candidate['figure_id'],
                'match' => $candidate['similarity'] === 100.0 ? 'exact' : 'similar',
            ];
        }

        return $suggestions;
    }

    /**
     * Files moved, renamed or overwritten outside the panel. One listing per folder,
     * so a level pays for the folders its figures use and not for each figure.
     *
     * @param  Collection<int, LevelContent>  $figures
     * @return list<array{figure_id: int, name: string, key: string, reason: 'missing'|'size_changed'}>
     */
    private function brokenLinks(Collection $figures): array
    {
        $linked = $figures->filter(fn (LevelContent $figure): bool => $figure->video_file['linked']);

        $sizes = $linked
            ->map(fn (LevelContent $figure): string => dirname($figure->video_file_path))
            ->unique()
            ->flatMap(fn (string $folder): array => array_column(
                $this->storage->browse($folder === '.' ? '' : $folder)['files'],
                'size_bytes',
                'key',
            ));

        return $linked
            ->filter(fn (LevelContent $figure): bool => $sizes->get($figure->video_file_path) !== $figure->video_file_size_bytes)
            ->map(fn (LevelContent $figure): array => [
                'figure_id' => $figure->id,
                'name' => $figure->name,
                'key' => $figure->video_file_path,
                'reason' => $sizes->has($figure->video_file_path) ? 'size_changed' : 'missing',
            ])
            ->values()
            ->all();
    }
}
