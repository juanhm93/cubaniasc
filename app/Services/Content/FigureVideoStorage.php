<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Enums\FigureVideoUrlPurpose;
use App\Exceptions\Content\FigureVideoStorageUnavailableException;
use App\Models\LevelContent;
use Aws\Exception\AwsException;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * The only place that talks to the bucket holding the figure videos (R2). Files
 * never pass through PHP: browsers upload and download them with signed URLs, and
 * the server only signs URLs, reads object metadata and deletes objects.
 */
final class FigureVideoStorage
{
    public const CONTENT_TYPE = 'video/mp4';

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
     * @return array{size_bytes: int, content_type: string|null, is_mp4: bool}|null
     */
    public function find(string $key): ?array
    {
        return $this->attempt(function (FilesystemAdapter $disk) use ($key): ?array {
            if (! $disk->exists($key)) {
                return null;
            }

            return [
                'size_bytes' => (int) $disk->size($key),
                'content_type' => $disk->mimeType($key) ?: null,
                'is_mp4' => $this->startsLikeMp4($disk, $key),
            ];
        });
    }

    /**
     * Best effort: a leftover object only costs storage, so a failed delete is
     * reported and never breaks the request.
     */
    public function delete(?string $key): void
    {
        if (blank($key) || ! $this->isConfigured()) {
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
        $expiresAt = now()->addMinutes($minutes);
        $filename = $this->filenameFor($content);

        $url = $this->attempt(fn (FilesystemAdapter $disk): string => $disk->temporaryUrl(
            $content->video_file_path,
            $expiresAt,
            [
                'ResponseContentType' => self::CONTENT_TYPE,
                'ResponseContentDisposition' => HeaderUtils::makeDisposition($purpose->disposition(), $filename),
            ],
        ));

        return ['url' => $url, 'expires_at' => $expiresAt, 'filename' => $filename];
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
