<?php

declare(strict_types=1);

namespace App\Services\Content\Concerns;

use App\Enums\MediaUrlPurpose;
use App\Exceptions\Content\MediaStorageUnavailableException;
use Aws\Exception\AwsException;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * What every service on the private media bucket shares: knowing whether the disk is
 * set up, turning its failures into one exception and signing GET URLs.
 */
trait InteractsWithMediaBucket
{
    public function isConfigured(): bool
    {
        return filled(config('filesystems.disks.'.$this->diskName().'.bucket'));
    }

    /**
     * @return array{url: string, expires_at: CarbonInterface}
     */
    private function presign(string $key, string $contentType, string $filename, MediaUrlPurpose $purpose, int $minutes): array
    {
        $expiresAt = now()->addMinutes($minutes);

        $url = $this->attempt(fn (FilesystemAdapter $disk): string => $disk->temporaryUrl(
            $key,
            $expiresAt,
            [
                'ResponseContentType' => $contentType,
                'ResponseContentDisposition' => HeaderUtils::makeDisposition($purpose->disposition(), $filename),
            ],
        ));

        return ['url' => $url, 'expires_at' => $expiresAt];
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
            throw MediaStorageUnavailableException::notConfigured($this->unavailableMessage());
        }

        try {
            return $operation(Storage::disk($this->diskName()));
        } catch (FilesystemException|AwsException $exception) {
            throw MediaStorageUnavailableException::unreachable($this->unavailableMessage(), $exception);
        }
    }

    abstract private function diskName(): string;

    abstract private function unavailableMessage(): string;
}
