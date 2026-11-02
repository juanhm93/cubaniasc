<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Enums\MediaUrlPurpose;
use App\Models\RecommendedSong;
use App\Services\Content\Concerns\InteractsWithMediaBucket;
use Carbon\CarbonInterface;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Str;
use League\Flysystem\FileAttributes;

/**
 * The only place that talks to the music folder of the bucket (R2). The folder is the
 * academy's: files are added by hand and the app only reads them, so there is no way
 * to delete or move anything here. Files never pass through PHP: players and
 * downloads use signed URLs.
 *
 * Keys go back and forth exactly as the bucket returns them. Some are in Unicode NFD,
 * and a normalized key would point to an object that does not exist.
 */
final class SongAudioStorage
{
    use InteractsWithMediaBucket;

    /** @var array<string, string> */
    private const CONTENT_TYPES = [
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
    ];

    public function folder(): string
    {
        return trim((string) config('cubania.songs.folder'), '/');
    }

    /**
     * Keys come back from the browser, so only supported files inside the music
     * folder are accepted, and anything the path normalizer would reject (`..`, empty
     * segments, control characters) is refused here.
     */
    public function isLibraryKey(string $key): bool
    {
        if (preg_match('/^[^\p{C}\\\\]+$/u', $key) !== 1 || ! str_starts_with($key, $this->folder().'/')) {
            return false;
        }

        return array_intersect(explode('/', $key), ['', '.', '..']) === []
            && $this->contentTypeOf($key) !== null;
    }

    /**
     * Every song file of the folder. A single listing brings the sizes too, so there
     * is no request per file. A folder that does not exist comes back empty.
     *
     * @return list<array{key: string, name: string, size_bytes: int}>
     */
    public function browse(): array
    {
        return $this->attempt(function (FilesystemAdapter $disk): array {
            $files = [];

            foreach ($disk->listContents($this->folder(), true) as $item) {
                if ($item instanceof FileAttributes && $this->isLibraryKey($item->path())) {
                    $files[] = [
                        'key' => $item->path(),
                        'name' => Str::afterLast($item->path(), '/'),
                        'size_bytes' => (int) $item->fileSize(),
                    ];
                }
            }

            usort($files, fn (array $first, array $second): int => strnatcasecmp($first['name'], $second['name']));

            return $files;
        });
    }

    /**
     * Size of a file, and whether its own first bytes match its extension: names and
     * declared types are whatever whoever uploaded it set. Null when it does not exist.
     *
     * @return array{size_bytes: int, is_audio: bool}|null
     */
    public function inspect(string $key): ?array
    {
        return $this->attempt(function (FilesystemAdapter $disk) use ($key): ?array {
            if (! $disk->exists($key)) {
                return null;
            }

            return [
                'size_bytes' => (int) $disk->size($key),
                'is_audio' => $this->startsLikeAudio($disk, $key),
            ];
        });
    }

    /**
     * A presigned GET for the song's file.
     *
     * @return array{url: string, expires_at: CarbonInterface, filename: string}
     */
    public function signedUrl(RecommendedSong $song, MediaUrlPurpose $purpose, int $minutes): array
    {
        $key = $song->audio_file_path;
        $filename = $this->filenameFor($song);

        return $this->presign($key, (string) $this->contentTypeOf($key), $filename, $purpose, $minutes) + ['filename' => $filename];
    }

    /**
     * A short presigned GET to listen to a file before creating its song. The name is
     * slugged because the disposition header only takes ASCII.
     *
     * @return array{url: string, expires_at: CarbonInterface}
     */
    public function previewUrl(string $key): array
    {
        $slug = Str::slug(pathinfo($key, PATHINFO_FILENAME)) ?: 'cancion';

        return $this->presign(
            $key,
            (string) $this->contentTypeOf($key),
            "{$slug}.{$this->extensionOf($key)}",
            MediaUrlPurpose::Stream,
            (int) config('cubania.songs.preview_url_minutes'),
        );
    }

    /**
     * ASCII name a person recognizes in their downloads folder.
     */
    public function filenameFor(RecommendedSong $song): string
    {
        $slug = Str::slug("{$song->artist} {$song->title}") ?: 'cancion';

        return "{$slug}.{$this->extensionOf($song->audio_file_path)}";
    }

    private function contentTypeOf(string $key): ?string
    {
        return self::CONTENT_TYPES[$this->extensionOf($key)] ?? null;
    }

    private function extensionOf(string $key): string
    {
        return Str::lower(Str::afterLast($key, '.'));
    }

    /**
     * Reads only the first bytes of the object; the stream is closed right away. An
     * MP3 opens with ID3 tags or directly with an MPEG frame, a WAV with its RIFF header.
     */
    private function startsLikeAudio(FilesystemAdapter $disk, string $key): bool
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

        if (strlen($header) < 12) {
            return false;
        }

        return match ($this->extensionOf($key)) {
            'mp3' => str_starts_with($header, 'ID3') || (ord($header[0]) === 0xFF && (ord($header[1]) & 0xE0) === 0xE0),
            'wav' => str_starts_with($header, 'RIFF') && substr($header, 8, 4) === 'WAVE',
            default => false,
        };
    }

    private function diskName(): string
    {
        return (string) config('cubania.songs.disk');
    }

    private function unavailableMessage(): string
    {
        return 'El almacenamiento de música no está disponible.';
    }
}
