<?php

declare(strict_types=1);

namespace App\Actions\Songs;

use App\Enums\MediaUrlPurpose;
use App\Models\RecommendedSong;
use App\Services\Content\SongAudioStorage;

/**
 * The signed URL a student gets to play or save a song, with what a client needs to
 * track the download. The web review and the mobile app answer with this same shape.
 */
final class CreateSongAudioUrl
{
    public function __construct(private readonly SongAudioStorage $storage) {}

    /**
     * @return array{url: string, expires_at: string, size_bytes: int|null, version: int, filename: string}
     */
    public function execute(RecommendedSong $song, MediaUrlPurpose $purpose): array
    {
        $minutes = match ($purpose) {
            MediaUrlPurpose::Download => config('cubania.songs.download_url_minutes'),
            MediaUrlPurpose::Stream => config('cubania.songs.stream_url_minutes'),
        };

        $signed = $this->storage->signedUrl($song, $purpose, (int) $minutes);

        return [
            'url' => $signed['url'],
            'expires_at' => $signed['expires_at']->toIso8601String(),
            'size_bytes' => $song->audio_file_size_bytes,
            'version' => $song->audio_file_version,
            'filename' => $signed['filename'],
        ];
    }
}
