<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\YouTubeVideo;
use PHPUnit\Framework\TestCase;

class YouTubeVideoTest extends TestCase
{
    public function test_extracts_id_from_watch_url(): void
    {
        $this->assertSame(
            's4DT0BFxDEk',
            YouTubeVideo::idFromUrl('https://www.youtube.com/watch?v=s4DT0BFxDEk'),
        );
    }

    public function test_extracts_id_from_short_and_embed_urls(): void
    {
        $this->assertSame(
            's4DT0BFxDEk',
            YouTubeVideo::idFromUrl('https://youtu.be/s4DT0BFxDEk'),
        );
        $this->assertSame(
            's4DT0BFxDEk',
            YouTubeVideo::idFromUrl('https://www.youtube.com/embed/s4DT0BFxDEk'),
        );
        $this->assertSame(
            's4DT0BFxDEk',
            YouTubeVideo::idFromUrl('https://www.youtube.com/shorts/s4DT0BFxDEk'),
        );
    }

    public function test_returns_null_for_invalid_urls(): void
    {
        $this->assertNull(YouTubeVideo::idFromUrl(''));
        $this->assertNull(YouTubeVideo::idFromUrl('https://example.com/video'));
        $this->assertNull(YouTubeVideo::idFromUrl('not-a-url'));
    }

    public function test_clamps_playback_rate_to_youtube_supported_values(): void
    {
        $this->assertSame(0.75, YouTubeVideo::clampPlaybackRate(0.75));
        $this->assertSame(0.5, YouTubeVideo::clampPlaybackRate(0.4));
        $this->assertSame(1.0, YouTubeVideo::clampPlaybackRate(1.05));
        $this->assertSame(2.0, YouTubeVideo::clampPlaybackRate(9));
    }
}
