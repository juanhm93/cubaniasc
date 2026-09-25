<?php

namespace Tests\Feature\Review;

use App\Models\Level;
use App\Models\RecommendedSong;
use App\Models\ReviewSession;
use App\Services\Review\SongRecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SongRecommendationServiceTest extends TestCase
{
    use RefreshDatabase;

    private SongRecommendationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SongRecommendationService::class);
    }

    public function test_recommends_active_songs_for_level(): void
    {
        $level = Level::factory()->create();
        $activeSong = RecommendedSong::factory()->create(['title' => 'Active Song']);
        $inactiveSong = RecommendedSong::factory()->inactive()->create(['title' => 'Inactive Song']);
        $otherLevelSong = RecommendedSong::factory()->create(['title' => 'Other Level Song']);

        $activeSong->levels()->attach($level);
        $inactiveSong->levels()->attach($level);
        $otherLevelSong->levels()->attach(Level::factory()->create());

        $songs = $this->service->recommendForLevel($level, 3);

        $this->assertCount(1, $songs);
        $this->assertTrue($activeSong->is($songs->first()));
    }

    public function test_assigns_up_to_three_songs_to_session(): void
    {
        $level = Level::factory()->create();
        $session = ReviewSession::factory()->create(['level_id' => $level->id]);
        $songs = RecommendedSong::factory()->count(4)->create();

        foreach ($songs as $song) {
            $song->levels()->attach($level);
        }

        $assigned = $this->service->assignToSession($session, 3);

        $this->assertCount(3, $assigned);
        $this->assertDatabaseCount('review_session_songs', 3);
        $this->assertSame(3, $session->fresh()->songs()->count());
    }
}
