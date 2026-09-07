<?php

declare(strict_types=1);

namespace App\Support;

final class CubaniaLanding
{
    /**
     * Shared Inertia payload for landing social links, hero video, and instructor cards.
     *
     * @return array{
     *     social: array{instagram: string, tiktok: string, whatsapp: string},
     *     hero: array{youtubeUrl: string, youtubeId: string|null, playbackRate: float},
     *     instructors: list<array{image: string}>
     * }
     */
    public static function shared(): array
    {
        $youtubeUrl = (string) config('cubania.hero.youtube_url');

        /** @var list<array{image?: mixed}> $instructors */
        $instructors = array_values((array) config('cubania.instructors', []));

        return [
            'social' => [
                'instagram' => (string) config('cubania.social.instagram'),
                'tiktok' => (string) config('cubania.social.tiktok'),
                'whatsapp' => (string) config('cubania.social.whatsapp'),
            ],
            'hero' => [
                'youtubeUrl' => $youtubeUrl,
                'youtubeId' => YouTubeVideo::idFromUrl($youtubeUrl),
                'playbackRate' => YouTubeVideo::clampPlaybackRate(
                    (float) config('cubania.hero.playback_rate'),
                ),
            ],
            'instructors' => array_map(
                static fn (array $instructor): array => [
                    'image' => (string) ($instructor['image'] ?? ''),
                ],
                $instructors,
            ),
        ];
    }
}
