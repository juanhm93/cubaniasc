<?php

declare(strict_types=1);

namespace App\Support;

final class YouTubeVideo
{
    /**
     * Playback rates accepted by the YouTube IFrame API.
     *
     * @var list<float>
     */
    public const PLAYBACK_RATES = [0.25, 0.5, 0.75, 1.0, 1.25, 1.5, 1.75, 2.0];

    public static function idFromUrl(string $url): ?string
    {
        $trimmed = trim($url);

        if ($trimmed === '') {
            return null;
        }

        $parts = parse_url($trimmed);

        if ($parts === false) {
            return null;
        }

        $host = $parts['host'] ?? '';
        $path = $parts['path'] ?? '';

        if ($host === 'youtu.be') {
            $id = explode('/', ltrim($path, '/'))[0] ?? '';

            return self::validId($id);
        }

        if (! str_contains($host, 'youtube.com') && ! str_contains($host, 'youtube-nocookie.com')) {
            return null;
        }

        parse_str($parts['query'] ?? '', $query);
        $v = $query['v'] ?? null;

        if (is_string($v)) {
            $id = self::validId($v);

            if ($id !== null) {
                return $id;
            }
        }

        if (preg_match('#/(?:embed|shorts)/([^/?]+)#', $path, $matches) === 1) {
            return self::validId($matches[1]);
        }

        return null;
    }

    public static function clampPlaybackRate(float $rate): float
    {
        $closest = 1.0;
        $bestDelta = PHP_FLOAT_MAX;

        foreach (self::PLAYBACK_RATES as $allowed) {
            $delta = abs($allowed - $rate);

            if ($delta < $bestDelta) {
                $bestDelta = $delta;
                $closest = $allowed;
            }
        }

        return $closest;
    }

    private static function validId(string $id): ?string
    {
        $id = trim($id);

        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $id) !== 1) {
            return null;
        }

        return $id;
    }
}
