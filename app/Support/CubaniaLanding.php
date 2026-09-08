<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\File;

final class CubaniaLanding
{
    /**
     * Shared Inertia payload for landing social links, hero video, and instructor cards.
     *
     * @return array{
     *     social: array{instagram: string, tiktok: string, whatsapp: string},
     *     hero: array{youtubeUrl: string, youtubeId: string|null, playbackRate: float},
     *     instructors: list<array{image: string}>,
     *     sliderImages: list<string>
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
            'sliderImages' => self::sliderImages(),
        ];
    }

    /**
     * @return list<string>
     */
    public static function sliderImages(): array
    {
        /** @var list<mixed> $configured */
        $configured = array_values((array) config('cubania.slider', []));

        $fromConfig = [];

        foreach ($configured as $image) {
            if (! is_string($image) || $image === '') {
                continue;
            }

            $fromConfig[] = $image;
        }

        if ($fromConfig !== []) {
            return $fromConfig;
        }

        return self::discoverSliderImages();
    }

    /**
     * @return list<string>
     */
    private static function discoverSliderImages(): array
    {
        $directory = public_path('cubania-assets/slider');

        if (! is_dir($directory)) {
            return [];
        }

        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'];
        $images = [];

        foreach (File::files($directory) as $file) {
            if (! in_array(strtolower($file->getExtension()), $allowed, true)) {
                continue;
            }

            $images[] = '/cubania-assets/slider/'.$file->getFilename();
        }

        natsort($images);

        return array_values($images);
    }
}
