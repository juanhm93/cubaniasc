<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Enums\FigureVideoUrlPurpose;
use App\Exceptions\Content\FigureVideoStorageUnavailableException;
use App\Models\Level;
use App\Models\LevelContent;
use Aws\Exception\AwsException;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * The only place that talks to the bucket holding the figure videos (R2). Files
 * never pass through PHP: browsers upload and download them with signed URLs, and
 * the server only signs URLs, reads object metadata and deletes objects.
 *
 * The bucket has two kinds of objects. Those under {@see self::MANAGED_PREFIX} were
 * uploaded from the panel and belong to the app. Everything else is the academy's
 * own folder tree: figures can link those files, but the app never deletes them.
 */
final class FigureVideoStorage
{
    public const CONTENT_TYPE = 'video/mp4';

    public const MANAGED_PREFIX = 'level-contents/';

    public function isConfigured(): bool
    {
        return filled(config('filesystems.disks.'.$this->diskName().'.bucket'));
    }

    public function maxUploadBytes(): int
    {
        return (int) config('cubania.figure_videos.max_upload_bytes');
    }

    public function newUploadKey(LevelContent $content): string
    {
        return "level-contents/{$content->id}/".Str::uuid()->toString().'.mp4';
    }

    /**
     * Keys come back from the browser on confirm, so only keys this service could
     * have issued for this same figure are accepted.
     */
    public function isUploadKeyFor(LevelContent $content, string $key): bool
    {
        $uuid = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

        return preg_match('#^level-contents/'.$content->id.'/'.$uuid.'\.mp4$#', $key) === 1;
    }

    public function isManagedKey(string $key): bool
    {
        return str_starts_with($key, self::MANAGED_PREFIX);
    }

    /**
     * Folders and keys of the library come back from the browser, so anything the
     * path normalizer would reject (`..`, empty segments, control characters) or
     * that points into the app's own uploads is refused here.
     */
    public function isLibraryPath(string $path): bool
    {
        if (preg_match('/^[^\p{C}\\\\]+$/u', $path) !== 1) {
            return false;
        }

        $segments = explode('/', $path);

        return array_intersect($segments, ['', '.', '..']) === []
            && $segments[0] !== rtrim(self::MANAGED_PREFIX, '/');
    }

    /**
     * Where the academy keeps a level's videos, e.g. `salsa-casino/avanzado-3`. Uses
     * the level's name because `levels.slug` has underscores and the folders do not.
     */
    public function defaultFolderFor(Level $level): string
    {
        return $level->danceType->slug.'/'.Str::slug($level->name);
    }

    /**
     * One level of the bucket: subfolders and MP4 files. A single listing brings the
     * sizes too, so there is no request per file. A folder that does not exist comes
     * back empty, and the app's own uploads are never listed.
     *
     * @return array{folders: list<string>, files: list<array{key: string, name: string, size_bytes: int}>}
     */
    public function browse(string $folder = ''): array
    {
        return $this->attempt(function (FilesystemAdapter $disk) use ($folder): array {
            $folders = [];
            $files = [];

            foreach ($disk->listContents($folder, false) as $item) {
                if ($item->isDir()) {
                    if ($item->path().'/' !== self::MANAGED_PREFIX) {
                        $folders[] = $item->path();
                    }

                    continue;
                }

                if ($item instanceof FileAttributes && Str::endsWith(Str::lower($item->path()), '.mp4')) {
                    $files[] = [
                        'key' => $item->path(),
                        'name' => basename($item->path()),
                        'size_bytes' => (int) $item->fileSize(),
                    ];
                }
            }

            sort($folders, SORT_NATURAL | SORT_FLAG_CASE);
            usort($files, fn (array $first, array $second): int => strnatcasecmp($first['name'], $second['name']));

            return ['folders' => $folders, 'files' => $files];
        });
    }

    /**
     * A presigned PUT. The browser must send `headers` with the file.
     *
     * @return array{url: string, headers: array<string, string>, expires_at: CarbonInterface}
     */
    public function createUploadUrl(string $key): array
    {
        $expiresAt = now()->addMinutes((int) config('cubania.figure_videos.upload_url_minutes'));

        $upload = $this->attempt(fn (FilesystemAdapter $disk): array => $disk->temporaryUploadUrl(
            $key,
            $expiresAt,
            ['ContentType' => self::CONTENT_TYPE],
        ));

        return [
            'url' => $upload['url'],
            'headers' => ['Content-Type' => self::CONTENT_TYPE],
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Size and content type of an uploaded object, or null when it does not exist.
     * `is_mp4` checks the file itself: the content type is only what the uploader
     * declared, while every MP4 starts with an `ftyp` box (bytes 4 to 8).
     *
     * @return array{size_bytes: int, is_mp4: bool, content_type: string|null}|null
     */
    public function find(string $key): ?array
    {
        $object = $this->inspect($key);

        if ($object === null) {
            return null;
        }

        return $object + [
            'content_type' => $this->attempt(fn (FilesystemAdapter $disk): ?string => $disk->mimeType($key) ?: null),
        ];
    }

    /**
     * Like {@see self::find()} without the declared content type: files that were
     * not uploaded from the panel may have none, and asking for it would fail.
     *
     * @return array{size_bytes: int, is_mp4: bool}|null
     */
    public function inspect(string $key): ?array
    {
        return $this->attempt(function (FilesystemAdapter $disk) use ($key): ?array {
            if (! $disk->exists($key)) {
                return null;
            }

            return [
                'size_bytes' => (int) $disk->size($key),
                'is_mp4' => $this->startsLikeMp4($disk, $key),
            ];
        });
    }

    /**
     * Best effort: a leftover object only costs storage, so a failed delete is
     * reported and never breaks the request. Only the app's own uploads can be
     * deleted: this is what keeps linked files safe, whoever calls it.
     */
    public function delete(?string $key): void
    {
        if (blank($key) || ! $this->isManagedKey($key) || ! $this->isConfigured()) {
            return;
        }

        try {
            Storage::disk($this->diskName())->delete($key);
        } catch (FilesystemException|AwsException $exception) {
            report($exception);
        }
    }

    /**
     * A presigned GET for the figure's MP4.
     *
     * @return array{url: string, expires_at: CarbonInterface, filename: string}
     */
    public function signedUrl(LevelContent $content, FigureVideoUrlPurpose $purpose, int $minutes): array
    {
        $filename = $this->filenameFor($content);

        return $this->presign($content->video_file_path, $filename, $purpose, $minutes) + ['filename' => $filename];
    }

    /**
     * A short presigned GET to watch a bucket file before linking it. The name is
     * slugged because the disposition header only takes ASCII.
     *
     * @return array{url: string, expires_at: CarbonInterface}
     */
    public function previewUrl(string $key): array
    {
        $slug = Str::slug(pathinfo($key, PATHINFO_FILENAME)) ?: 'figura';

        return $this->presign(
            $key,
            "{$slug}.mp4",
            FigureVideoUrlPurpose::Stream,
            (int) config('cubania.figure_videos.preview_url_minutes'),
        );
    }

    /**
     * ASCII name with the id and version, so two versions of the same figure never clash.
     */
    public function filenameFor(LevelContent $content): string
    {
        $slug = Str::slug($content->name) ?: 'figura';

        return "{$slug}-{$content->id}-v{$content->video_file_version}.mp4";
    }

    /**
     * @return array{url: string, expires_at: CarbonInterface}
     */
    private function presign(string $key, string $filename, FigureVideoUrlPurpose $purpose, int $minutes): array
    {
        $expiresAt = now()->addMinutes($minutes);

        $url = $this->attempt(fn (FilesystemAdapter $disk): string => $disk->temporaryUrl(
            $key,
            $expiresAt,
            [
                'ResponseContentType' => self::CONTENT_TYPE,
                'ResponseContentDisposition' => HeaderUtils::makeDisposition($purpose->disposition(), $filename),
            ],
        ));

        return ['url' => $url, 'expires_at' => $expiresAt];
    }

    /**
     * Reads only the first bytes of the object; the stream is closed right away.
     */
    private function startsLikeMp4(FilesystemAdapter $disk, string $key): bool
    {
        $stream = $disk->readStream($key);

        if (! is_resource($stream)) {
            return false;
        }

        try {
            $header = (string) fread($stream, 12);
        } finally {
            fclose($stream);
        }

        return strlen($header) >= 8 && substr($header, 4, 4) === 'ftyp';
    }

    /**
     * @template TResult
     *
     * @param  Closure(FilesystemAdapter): TResult  $operation
     * @return TResult
     */
    private function attempt(Closure $operation): mixed
    {
        if (! $this->isConfigured()) {
            throw FigureVideoStorageUnavailableException::notConfigured();
        }

        try {
            return $operation(Storage::disk($this->diskName()));
        } catch (FilesystemException|AwsException $exception) {
            throw FigureVideoStorageUnavailableException::unreachable($exception);
        }
    }

    private function diskName(): string
    {
        return (string) config('cubania.figure_videos.disk');
    }
}
